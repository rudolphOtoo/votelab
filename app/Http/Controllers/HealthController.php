<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * GET /health for uptime monitors (D-109). 503 when the app can't take votes safely (database or the shared
 * lock store failing); 200 "degraded" when the queue is backing up, jobs failed recently, or the scheduler
 * stopped. No secrets, no vote data. Laravel's /up (process alive) stays as it is.
 */
final class HealthController
{
    public const string HEARTBEAT_KEY = 'health:scheduler_heartbeat';

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => self::database(),
            'locks' => self::locks(),
            'queue' => self::queue(),
            'failed_jobs_24h' => self::failedJobs(),
            'scheduler' => self::scheduler(),
        ];

        $down = !$checks['database']['ok'] || !$checks['locks']['ok'];
        $degraded = !$checks['queue']['ok'] || !$checks['failed_jobs_24h']['ok'] || !$checks['scheduler']['ok'];

        return new JsonResponse(
            [
                'status' => match (true) {
                    $down => 'down',
                    $degraded => 'degraded',
                    default => 'ok',
                },
                'checks' => $checks,
            ],
            $down ? 503 : 200,
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @return array{ok: bool}
     */
    private static function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    /**
     * @return array{ok: bool}
     */
    private static function locks(): array
    {
        try {
            $lock = Cache::lock('health:lock-probe', 5);
            $ok = $lock->get() !== false;
            $lock->release();

            return ['ok' => $ok];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    /**
     * @return array{ok: bool, pending: int|null, oldest_seconds: int|null}
     */
    private static function queue(): array
    {
        if (config('queue.default') !== 'database' || !Schema::hasTable('jobs')) {
            return ['ok' => true, 'pending' => null, 'oldest_seconds' => null];
        }

        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $age = $oldest === null ? 0 : max(0, (int) now()->timestamp - (int) $oldest);

        return ['ok' => $age < 300, 'pending' => $pending, 'oldest_seconds' => $age];
    }

    /**
     * @return array{ok: bool, count: int|null}
     */
    private static function failedJobs(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['ok' => true, 'count' => null];
        }

        $count = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        return ['ok' => $count === 0, 'count' => $count];
    }

    /**
     * @return array{ok: bool, last_run_seconds_ago: int|null}
     */
    private static function scheduler(): array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        $ago = is_int($last) ? max(0, (int) now()->timestamp - $last) : null;

        return ['ok' => $ago !== null && $ago < 300, 'last_run_seconds_ago' => $ago];
    }
}
