<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Livewire\Public\ProgramLanding;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\LeaderboardCache;
use Livewire\Livewire;

/** Distinctive counts, so any appearance in HTML or the Livewire snapshot is detectable. */
const SEARCH_LEAK_COUNTS = ['Zara Owusu' => 987_654, 'Abena Mensah' => 123_457, 'Kofi Boateng' => 555_321];

function searchProgram(CategoryStatus $status = CategoryStatus::Embargoed): AwardProgram
{
    $organization = Organization::factory()->create(['slug' => 'campus']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    $category = AwardCategory::factory()->for($program)->status($status)->create(['name' => 'Artiste of the Year']);

    $codes = ['Zara Owusu' => 'ZO11', 'Abena Mensah' => 'AM27', 'Kofi Boateng' => 'KB30'];

    foreach (SEARCH_LEAK_COUNTS as $name => $votes) {
        Nominee::factory()
            ->for($category)
            ->create(['name' => $name, 'stage_name' => null, 'nominee_code' => $codes[$name]])
            ->forceFill(['votes_count' => $votes])
            ->save();
    }

    LeaderboardCache::refresh($category->id);

    return $program;
}

function landing(AwardProgram $program): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(ProgramLanding::class, [
        'organization' => $program->organization,
        'awardProgram' => $program,
    ]);
}

it('goes straight to the nominee when the search is their code, with or without JavaScript', function (): void {
    searchProgram();

    // No JavaScript: the form submits ?q= to the landing page.
    $this->get('/campus/awards?q=am-27')->assertRedirect('/campus/awards/AM27');

    landing(AwardProgram::query()->sole())->set('q', ' am27 ')->call('find')->assertRedirect('/campus/awards/AM27');
});

it('lists matching nominees by name, never by votes', function (): void {
    $program = searchProgram();

    landing($program)
        ->set('q', 'o') // too short: no list
        ->assertDontSee('Zara Owusu')
        ->set('q', 'en') // Abena Mensah, Kofi Boateng
        ->assertSeeInOrder(['Abena Mensah', 'Kofi Boateng'])
        ->assertDontSee('Zara Owusu')
        ->set('q', 'zz')
        ->assertSee('No nominee matches');
});

it('never finds withdrawn nominees, draft categories or other programs', function (): void {
    $program = searchProgram();
    Nominee::query()->where('nominee_code', 'KB30')->update(['archived_at' => now()]);
    $draft = AwardCategory::factory()->for($program)->status(CategoryStatus::Draft)->create();
    Nominee::factory()->for($draft)->create(['name' => 'Secret Draftee', 'nominee_code' => 'SD01']);
    $other = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    Nominee::factory()->for($other)->create(['name' => 'Elsewhere Nominee', 'nominee_code' => 'EN01']);

    landing($program)
        ->set('q', 'Kofi')
        ->assertSee('No nominee matches')
        ->set('q', 'Secret')
        ->assertSee('No nominee matches')
        ->set('q', 'Elsewhere')
        ->assertSee('No nominee matches');

    $this->get('/campus/awards?q=SD01')->assertOk()->assertDontSee('/campus/awards/SD01');
    $this->get('/campus/awards?q=EN01')->assertOk();
});

it('never sends vote counts from the landing page or its search while results are hidden', function (CategoryStatus $status): void {
    $program = searchProgram($status);

    $html = $this->get('/campus/awards?q=a')->assertOk()->getContent();
    $component = landing($program)->set('q', 'Mensah');

    foreach ([$html, $component->html(), json_encode($component->snapshot, JSON_THROW_ON_ERROR)] as $content) {
        foreach (SEARCH_LEAK_COUNTS as $votes) {
            expect($content)->not->toContain((string) $votes)->not->toContain(number_format($votes));
        }

        expect($content)->not->toContain('votes_count');
    }
})->with([
    'results hidden' => CategoryStatus::Embargoed,
    'closed' => CategoryStatus::Closed,
    'open (public results still never shown on the landing page)' => CategoryStatus::Open,
]);
