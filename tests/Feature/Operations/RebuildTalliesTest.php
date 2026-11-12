<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteRecord;
use App\Support\LeaderboardCache;

/**
 * A program whose first nominee's counter (99) drifted from its ledger (10).
 *
 * @return array{AwardProgram, Nominee, Nominee}
 */
function driftedProgram(): array
{
    $organization = Organization::factory()->create(['slug' => 'org']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create();
    $drifted = Nominee::factory()->for($category)->create(['votes_count' => 99]);
    $fine = Nominee::factory()->for($category)->create(['votes_count' => 7]);
    VoteRecord::factory()->for($drifted)->create(['quantity' => 10, 'is_paid' => true]);
    VoteRecord::factory()->for($fine)->create(['quantity' => 7, 'is_paid' => false]);

    return [$program, $drifted, $fine];
}

it('reports drift on a dry run without changing anything, and exits 1', function (): void {
    [$program, $drifted] = driftedProgram();

    $this
        ->artisan('votes:rebuild-tallies', ['program' => 'org/awards', '--dry-run' => true])
        ->expectsTable(['Program', 'Category', 'Nominee', 'Counter', 'Ledger', 'Action'], [[
            $program->id,
            $drifted->awardCategory->name,
            $drifted->id,
            99,
            10,
            'reported',
        ]])
        ->assertExitCode(1);

    expect($drifted->fresh()?->votes_count)
        ->toBe(99)
        ->and(AuditEvent::query()->where('action', 'tally.drift_found')->count())
        ->toBe(1);
});

it('fixes counters from the ledger and refreshes the leaderboard', function (): void {
    [$program, $drifted, $fine] = driftedProgram();
    LeaderboardCache::standings($drifted->awardCategory);

    $this->artisan('votes:rebuild-tallies', ['program' => (string) $program->id])->assertExitCode(1);

    expect($drifted->fresh()?->votes_count)
        ->toBe(10)
        ->and($fine->fresh()?->votes_count)
        ->toBe(7)
        ->and(collect(LeaderboardCache::standings($drifted->awardCategory))->sortKeys()->all())
        ->toBe([$drifted->id => 10, $fine->id => 7]);

    $this
        ->artisan('votes:rebuild-tallies', ['program' => (string) $program->id])
        ->expectsOutputToContain('No drift')
        ->assertExitCode(0);
});

it('alerts on drift found by the nightly --all dry run', function (): void {
    $alerts = captureLog('alerts');
    driftedProgram();
    AwardProgram::factory()->create(['status' => ProgramStatus::Archived]);

    $this->artisan('votes:rebuild-tallies', ['--all' => true, '--dry-run' => true])->assertExitCode(1);

    expect($alerts->hasErrorThatContains('tally.drift_nightly'))
        ->toBeTrue()
        ->and($alerts->hasErrorThatContains('tally.drift'))
        ->toBeTrue();
});

it('refuses to change counters in production without --force', function (): void {
    [$program, $drifted] = driftedProgram();
    app()->detectEnvironment(static fn(): string => 'production');

    $this
        ->artisan('votes:rebuild-tallies', ['program' => (string) $program->id])
        ->expectsOutputToContain('--force')
        ->assertExitCode(1);
    expect($drifted->fresh()?->votes_count)->toBe(99);
});

it('needs a program or --all, and a real program', function (): void {
    $this->artisan('votes:rebuild-tallies')->assertExitCode(1);
    $this->artisan('votes:rebuild-tallies', ['program' => 'nope/missing'])->assertExitCode(1);
});

it('is scheduled nightly as a dry run on one server', function (): void {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(static fn($e): bool => str_contains((string) $e->command, 'votes:rebuild-tallies'));

    expect((string) $event?->command)
        ->toContain('--all')
        ->toContain('--dry-run')
        ->and($event?->expression)
        ->toBe('0 3 * * *')
        ->and($event?->onOneServer)
        ->toBeTrue();
});
