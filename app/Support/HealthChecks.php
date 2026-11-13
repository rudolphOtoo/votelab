<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The individual checks behind GET /health (HealthController).
 */
final class HealthChecks
{
    /**
     * @return array{ok: bool}
     */
    public static function database(): array
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
    public static function locks(): array
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
    public static function queue(): array
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
    public static function failedJobs(): array
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
    public static function scheduler(): array
    {
        $last = Cache::get(HealthController::HEARTBEAT_KEY);
        $ago = is_int($last) ? max(0, (int) now()->timestamp - $last) : null;

        return ['ok' => $ago !== null && $ago < 300, 'last_run_seconds_ago' => $ago];
    }
}
