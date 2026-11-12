<?php

declare(strict_types=1);

use App\Actions\Reporting\ExportResultsAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function exportCsv(AwardProgram $program, App\Models\User $actor, ?AwardCategory $category = null): string
{
    $response = app(ExportResultsAction::class)->execute($program, $actor, $category);
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * @return array{AwardProgram, AwardCategory, Nominee, Nominee}
 */
function exportFixture(): array
{
    $program = AwardProgram::factory()->create(['title' => 'Campus Awards']);
    $category = AwardCategory::factory()
        ->for($program)
        ->status(CategoryStatus::Embargoed)
        ->create(['name' => 'Best Artist']);
    $first = Nominee::factory()->for($category)->create([
        'name' => 'Ama Mensah',
        'nominee_code' => 'AM27',
        'votes_count' => 999,
        'display_order' => 1,
    ]);
    $second = Nominee::factory()->for($category)->create([
        'name' => 'Kofi Boateng',
        'nominee_code' => 'KB33',
        'display_order' => 2,
        'archived_at' => now(),
    ]);
    VoteRecord::factory()->for($first)->create(['quantity' => 40, 'is_paid' => true]);
    VoteRecord::factory()->for($first)->create(['quantity' => 2, 'is_paid' => false]);

    return [$program, $category, $first, $second];
}

it('exports the required header columns and ledger totals, not the derived counter', function (): void {
    [$program] = exportFixture();

    $csv = exportCsv($program, organizer(OrganizationRole::Admin, $program->organization));
    expect($csv)->toStartWith("\u{FEFF}"); // UTF-8 BOM, so Excel shows names like "Ɛsi" correctly.
    $lines = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));

    expect($lines[0])
        ->toBe(['Category', 'Nominee Code', 'Candidate Name', 'Total Votes', 'Archived'])
        ->and($lines[1])
        ->toBe(['Best Artist', 'AM27', 'Ama Mensah', '42', 'no'])
        ->and($lines[2])
        ->toBe(['Best Artist', 'KB33', 'Kofi Boateng', '0', 'yes']);
});

it('holds no voter personal data', function (): void {
    [$program, , $first] = exportFixture();
    App\Models\VoteTransaction::factory()->for($first)->create(['voter_email' => 'voter@example.com']);

    $csv = exportCsv($program, organizer(OrganizationRole::Owner, $program->organization));

    expect($csv)->not->toContain('voter@example.com')->not->toContain('ip_hash');
});

it('audits every export with its scope and whether hidden results were included', function (): void {
    [$program, $category] = exportFixture();
    $admin = organizer(OrganizationRole::Admin, $program->organization);

    exportCsv($program, $admin);
    exportCsv($program, $admin, $category);

    $events = AuditEvent::query()->where('action', 'results.exported')->orderBy('id')->get();
    expect($events)
        ->toHaveCount(2)
        ->and($events[0]->actor_user_id)
        ->toBe($admin->id)
        ->and($events[0]->context)
        ->toMatchArray(['scope' => 'program', 'included_hidden_results' => true])
        ->and($events[1]->context)
        ->toMatchArray(['scope' => 'category', 'award_category_id' => $category->id]);
});

it('refuses staff and outsiders, and categories of another program', function (): void {
    [$program] = exportFixture();

    expect(fn() => exportCsv($program, organizer(OrganizationRole::Staff, $program->organization)))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => exportCsv($program, organizer()))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => exportCsv(
            $program,
            organizer(OrganizationRole::Owner, $program->organization),
            AwardCategory::factory()->create(),
        ))
        ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('aggregates in SQL and streams rows, so large ledgers do not load into memory', function (): void {
    [$program, , $first] = exportFixture();
    $now = now()->toDateTimeString();
    $row = [
        'organization_id' => $first->organization_id,
        'award_program_id' => $program->id,
        'award_category_id' => $first->award_category_id,
        'nominee_id' => $first->id,
        'is_paid' => false,
        'quantity' => 1,
        'cast_at' => $now,
    ];
    for ($batch = 0; $batch < 20; $batch++) {
        DB::table('vote_records')->insert(array_fill(0, 1_000, $row));
    }
    $selects = [];
    DB::listen(static function ($query) use (&$selects): void {
        $selects[] = $query->sql;
    });

    $csv = exportCsv($program, organizer(OrganizationRole::Owner, $program->organization));

    expect($csv)
        ->toContain('AM27,"Ama Mensah",20042')
        ->and(collect($selects)->filter(fn(string $sql): bool => str_contains($sql, 'from "nominees"'))->first())
        ->toContain('SUM(quantity)');
});

it('keeps an archived nominee with the most votes in the export, marked archived', function (): void {
    [$program, , , $archived] = exportFixture();
    App\Models\VoteRecord::factory()->for($archived)->create(['quantity' => 500, 'is_paid' => true]);

    $csv = exportCsv($program, organizer(OrganizationRole::Owner, $program->organization));

    expect($csv)->toContain('KB33,"Kofi Boateng",500,yes');
});
