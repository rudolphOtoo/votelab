<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Livewire\Public\CategoryShow;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\LeaderboardCache;
use Livewire\Livewire;

/**
 * Distinctive counts, so any appearance in HTML, attributes or the Livewire snapshot is detectable.
 */
const LEAK_COUNTS = [987_654, 123_457];

function categoryWithCounts(CategoryStatus $status): AwardCategory
{
    $organization = Organization::factory()->create(['slug' => 'org']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    $category = AwardCategory::factory()->for($program)->status($status)->create(['slug' => 'cat']);

    foreach (LEAK_COUNTS as $i => $votes) {
        Nominee::factory()
            ->for($category)
            ->create(['nominee_code' => "N0{$i}"])
            ->forceFill(['votes_count' => $votes])
            ->save();
    }

    LeaderboardCache::refresh($category->id); // Counts are sitting in the cache too: still must not leak.

    return $category;
}

function assertNoCounts(string $content): void
{
    foreach (LEAK_COUNTS as $votes) {
        expect($content)
            ->not->toContain((string) $votes)->and($content)
            ->not->toContain(number_format($votes));
    }

    expect($content)->not->toContain('votes_count');
}

it('never sends vote counts for a hidden-results category: HTML, attributes or Livewire snapshot', function (CategoryStatus $status): void {
    categoryWithCounts($status);

    $page = $this->get('/org/awards/categories/cat')->assertOk();
    assertNoCounts($page->getContent());

    // Deep link with the drawer open.
    assertNoCounts($this->get('/org/awards/N00')->getContent());
})->with([
    'embargoed' => CategoryStatus::Embargoed,
    'closed' => CategoryStatus::Closed,
]);

it('never sends vote counts on a poll of a hidden-results category', function (): void {
    $category = categoryWithCounts(CategoryStatus::Embargoed);

    $component = Livewire::test(CategoryShow::class, [
        'organization' => $category->awardProgram->organization,
        'awardProgram' => $category->awardProgram,
        'category' => $category,
    ])->call('refreshStandings');

    assertNoCounts($component->html());
    assertNoCounts(json_encode($component->snapshot, JSON_THROW_ON_ERROR));
});

it('stops showing counts the moment a category goes blind', function (): void {
    $category = categoryWithCounts(CategoryStatus::Open);
    $component = Livewire::test(CategoryShow::class, [
        'organization' => $category->awardProgram->organization,
        'awardProgram' => $category->awardProgram,
        'category' => $category,
    ])->assertSee('987,654');

    $category->forceFill(['status' => CategoryStatus::Embargoed])->save();

    assertNoCounts($component->call('refreshStandings')->html());
});

it('shows counts while the leaderboard is public', function (CategoryStatus $status): void {
    categoryWithCounts($status);

    $this->get('/org/awards/categories/cat')->assertOk()->assertSee('987,654 votes');
})->with([
    'open' => CategoryStatus::Open,
    'revealed' => CategoryStatus::Revealed,
]);

it('polls quickly only while counts are public', function (): void {
    categoryWithCounts(CategoryStatus::Open);
    $this->get('/org/awards/categories/cat')->assertSee('wire:poll.3s.visible', escape: false);

    AwardCategory::query()->update(['status' => CategoryStatus::Embargoed]);
    $this->get('/org/awards/categories/cat')->assertSee('wire:poll.15s.visible', escape: false);
});
