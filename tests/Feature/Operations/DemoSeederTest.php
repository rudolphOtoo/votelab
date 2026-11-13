<?php

declare(strict_types=1);

use App\Actions\Admin\TallyAudit;
use App\Models\AwardProgram;
use App\Models\Nominee;
use Database\Seeders\DatabaseSeeder;

it('seeds a demo whose counters match the vote ledger exactly: zero drift', function (): void {
    $this->seed(DatabaseSeeder::class);

    $programs = AwardProgram::query()->get();

    expect($programs)
        ->not
        ->toBeEmpty()
        ->and((int) Nominee::query()->sum('votes_count'))
        ->toBeGreaterThan(0)
        ->and(TallyAudit::run($programs, dryRun: true))
        ->toBeEmpty();
});
