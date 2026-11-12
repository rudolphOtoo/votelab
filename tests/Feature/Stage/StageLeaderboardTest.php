<?php

declare(strict_types=1);

use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Livewire\Stage\StageLeaderboard;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteRecord;
use Livewire\Livewire;

/**
 * Distinctive values: any appearance of a count, name or code in hidden scenes is detectable.
 *
 * @return array{AwardProgram, AwardCategory, list<Nominee>}
 */
function stageProgram(CategoryStatus $status, array $votes = [731_542, 268_913, 154_277]): array
{
    $organization = Organization::factory()->create(['slug' => 'org', 'name' => 'Accra Creatives']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards', 'title' => 'Campus Awards']);
    $category = AwardCategory::factory()
        ->for($program)
        ->status($status)
        ->create(['slug' => 'artist', 'name' => 'Best Artist']);
    $nominees = [];

    foreach ($votes as $i => $count) {
        $nominee = Nominee::factory()->for($category)->create([
            'name' => "Stagename{$i} Person",
            'nominee_code' => 'ZQ' . (22 + $i),
            'votes_count' => $count,
            'display_order' => $i,
        ]);
        VoteRecord::factory()->for($nominee)->create(['quantity' => $count, 'is_paid' => false]);
        $nominees[] = $nominee;
    }

    return [$program, $category, $nominees];
}

function stage(AwardProgram $program): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(StageLeaderboard::class, [
        'organization' => $program->organization,
        'awardProgram' => $program,
    ]);
}

/**
 * Nothing a hidden scene may contain: counts (raw or formatted), nominee names or codes, or podium markup.
 */
function assertNothingRevealed(string $content): void
{
    foreach ([
        '731542',
        '731,542',
        '268913',
        '268,913',
        '154277',
        'Stagename',
        'ZQ22',
        'ZQ23',
        'ZQ24',
        'Winner',
        'wire:key="podium-',
        'wire:key="bar-',
        'stageReveal',
    ] as $leak) {
        expect($content)->not->toContain($leak);
    }
}

it('needs no login and shows live bars for an open category, highest first', function (): void {
    [$program] = stageProgram(CategoryStatus::Open);

    $html = (string) $this->get('/stage/org/awards')->assertOk()->getContent();

    expect($html)
        ->toContain('data-theme="stage"')
        ->toContain('731,542')
        ->and(strpos($html, 'Stagename0'))
        ->toBeLessThan(strpos($html, 'Stagename1'))
        ->and(strpos($html, 'Stagename1'))
        ->toBeLessThan(strpos($html, 'Stagename2'));
});

it('reveals nothing before the reveal: no counts, names, order or podium in HTML or the Livewire payload', function (
    CategoryStatus $status,
    string $message,
): void {
    [$program] = stageProgram($status);

    $page = (string) $this->get('/stage/org/awards')->assertOk()->assertSee($message)->getContent();
    assertNothingRevealed($page);

    $component = stage($program)->call('poll');
    assertNothingRevealed($component->html());
    assertNothingRevealed(json_encode($component->snapshot, JSON_THROW_ON_ERROR));
})->with([
    'embargoed' => [CategoryStatus::Embargoed, 'Official Blind Voting Phase Active — Scores Hidden Until Finale'],
    'closed' => [CategoryStatus::Closed, 'Voting closed — results coming up'],
]);

it('stops showing counts on the next poll once the category goes blind', function (): void {
    [$program, $category] = stageProgram(CategoryStatus::Open);
    $screen = stage($program)->assertSee('731,542');

    $category->forceFill(['status' => CategoryStatus::Embargoed])->save();

    assertNothingRevealed($screen->call('poll')->html());
});

it('plays the reveal with the winner after RevealWinnerAction, and only then', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    [$program, $category] = stageProgram(CategoryStatus::Closed);
    $category->forceFill(['closed_at' => now()->subHour()])->save();
    $screen = stage($program);
    assertNothingRevealed($screen->html());

    app(RevealWinnerAction::class)->execute($category, organizer(OrganizationRole::Owner, $program->organization));

    $html = $screen->call('poll')->html();
    expect($html)
        ->toContain('Winner')
        ->toContain('stageReveal')
        ->toContain('Stagename0 Person')
        ->toContain('731,542 votes')
        ->and(substr_count($html, 'ring-[#FBBF24]'))
        ->toBe(1);
});

it('crowns joint winners on a tie', function (): void {
    [$program] = stageProgram(CategoryStatus::Revealed, [500, 500, 20]);

    $html = stage($program)->html();

    expect($html)->toContain('Joint winners')->and(substr_count($html, 'ring-[#FBBF24]'))->toBe(2);
});

it('leaves archived nominees off the stage, even with the most votes', function (CategoryStatus $status): void {
    [$program, , $nominees] = stageProgram($status);
    $nominees[0]->forceFill(['archived_at' => now()])->save();

    $html = stage($program)->html();

    expect($html)->not->toContain('Stagename0')->not->toContain('731,542')->toContain('Stagename1');
})->with([CategoryStatus::Open, CategoryStatus::Revealed]);

it('spotlights the category whose status changed last, or the one pinned in the URL', function (): void {
    [$program, $first] = stageProgram(CategoryStatus::Open);
    $second = AwardCategory::factory()
        ->for($program)
        ->status(CategoryStatus::Open)
        ->create(['slug' => 'dancer', 'name' => 'Best Dancer']);
    $owner = organizer(OrganizationRole::Owner, $program->organization);

    app(TransitionCategoryStatusAction::class)->execute($second, CategoryStatus::Embargoed, $owner);
    expect(stage($program)->html())->toContain('Best Dancer')->toContain('Official Blind Voting');

    $this->get('/stage/org/awards?category=artist')->assertSee('Best Artist')->assertSee('731,542');
});

it('ignores a pinned draft category', function (): void {
    [$program] = stageProgram(CategoryStatus::Open);
    AwardCategory::factory()
        ->for($program)
        ->status(CategoryStatus::Draft)
        ->create(['slug' => 'secret', 'name' => 'Secret Draft']);

    $this
        ->get('/stage/org/awards?category=secret')
        ->assertOk()
        ->assertDontSee('Secret Draft')
        ->assertSee('Best Artist');
});

it('404s for unpublished or archived programs and mismatched organizations', function (ProgramStatus $status): void {
    [$program] = stageProgram(CategoryStatus::Open);
    $program->forceFill(['status' => $status])->save();

    $this->get('/stage/org/awards')->assertNotFound();
})->with([ProgramStatus::Draft, ProgramStatus::Archived]);

it('404s a program under another organization\'s slug', function (): void {
    stageProgram(CategoryStatus::Open);
    Organization::factory()->create(['slug' => 'other']);

    $this->get('/stage/other/awards')->assertNotFound();
});

it('rate limits page loads per network', function (): void {
    config(['voting.rate_limits.stage_per_ip' => 2]);
    stageProgram(CategoryStatus::Open);

    $this->get('/stage/org/awards')->assertOk();
    $this->get('/stage/org/awards')->assertOk();
    $this->get('/stage/org/awards')->assertTooManyRequests();
});

it('keeps the last good screen when a poll is throttled', function (): void {
    config(['voting.rate_limits.stage_per_ip' => 1]);
    [$program, $category] = stageProgram(CategoryStatus::Open);
    $screen = stage($program)->call('poll')->assertSee('Best Artist'); // Uses the one allowed poll.
    $category->forceFill(['name' => 'Renamed Meanwhile'])->save();

    $screen->call('poll')->assertSee('Best Artist')->assertDontSee('Renamed Meanwhile');
});

it('ships the reduced-motion, wake-lock and quiet-failure hooks', function (): void {
    [$program] = stageProgram(CategoryStatus::Open);

    expect(stage($program)->html())
        ->toContain('motion-reduce:transition-none')
        ->toContain('x-data="stageScreen"')
        ->toContain('data-stage-screen');

    $script = (string) file_get_contents(resource_path('js/stage.js'));
    expect($script)
        ->toContain("navigator.wakeLock.request('screen')")
        ->toContain('prefers-reduced-motion: reduce')
        ->toContain('preventDefault()');
});
