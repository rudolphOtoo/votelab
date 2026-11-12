<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catch payments whose webhook never arrived (D-39). Requires the scheduler cron in production.
Schedule::command('payments:reconcile-pending')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Failed jobs keep their payload, and a failed invitation or password-reset email carries its one-time
// link. Drop failed jobs after 7 days so those links don't linger (failed_jobs is for debugging only).
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->onOneServer();

// Nightly ledger audit (D-109): report any nominee whose counter drifted from vote_records; drift alerts.
Schedule::command('votes:rebuild-tallies', ['--all' => true, '--dry-run' => true])
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Scheduler heartbeat for /health: if this stops, sweeps, pruning and the nightly audit have stopped too.
Schedule::call(static fn() => Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp, 3600))
    ->name('health-heartbeat')
    ->everyMinute()
    ->onOneServer();
