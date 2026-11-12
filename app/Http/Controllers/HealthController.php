<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HealthChecks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /health for uptime monitors (D-109, D-111). 503 when the app can't take votes safely (database or the
 * shared lock store failing); 200 "degraded" when the queue is backing up, jobs failed recently, or the
 * scheduler stopped. The public response is only {"status": …} and the HTTP code; the individual checks
 * (which failed, queue depth, last heartbeat) are included only when the X-Health-Token header matches
 * HEALTH_CHECK_TOKEN (constant-time comparison; never when no token is configured). Laravel's /up stays.
 */
final class HealthController
{
    public const string HEARTBEAT_KEY = 'health:scheduler_heartbeat';

    public const string TOKEN_HEADER = 'X-Health-Token';

    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => HealthChecks::database(),
            'locks' => HealthChecks::locks(),
            'queue' => HealthChecks::queue(),
            'failed_jobs_24h' => HealthChecks::failedJobs(),
            'scheduler' => HealthChecks::scheduler(),
        ];

        $down = !$checks['database']['ok'] || !$checks['locks']['ok'];
        $degraded = !$checks['queue']['ok'] || !$checks['failed_jobs_24h']['ok'] || !$checks['scheduler']['ok'];

        $status = match (true) {
            $down => 'down',
            $degraded => 'degraded',
            default => 'ok',
        };

        return new JsonResponse(
            self::authorized($request) ? ['status' => $status, 'checks' => $checks] : ['status' => $status],
            $down ? 503 : 200,
            ['Cache-Control' => 'no-store'],
        );
    }

    private static function authorized(Request $request): bool
    {
        $expected = config('app.health_check_token');
        $given = $request->header(self::TOKEN_HEADER);

        return is_string($expected) && $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }
}
