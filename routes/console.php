<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catch payments whose webhook never arrived (D-39). Requires the scheduler cron in production.
Schedule::command('payments:reconcile-pending')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Failed jobs keep their payload, and a failed invitation or password-reset email carries its one-time
// link. Drop failed jobs after 7 days so those links don't linger (failed_jobs is for debugging only).
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->onOneServer();
