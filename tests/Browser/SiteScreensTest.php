<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;

/*
 * The public site in a real browser (homepage brief, Step B): every page at 360, 768 and 1440 px with axe at every
 * impact level, plain JavaScript only, the homepage's first screen on a phone, and the header turning solid on scroll.
 * Screenshots for review in tests/Browser/Screenshots.
 */

// Sections fade up as they enter: measure the finished page, never a half-faded one.
const SITE_SECTIONS_SHOWN =
    '[...document.querySelectorAll("[data-reveal]")].every('
    . '(s) => s.hasAttribute("data-revealed") || s.getBoundingClientRect().top > innerHeight * 0.9)'
    . ' && [...document.querySelectorAll("[data-reveal][data-revealed]")].every((s) => getComputedStyle(s).opacity === "1")';

beforeEach(function (): void {
    $program = AwardProgram::factory()->create([
        'title' => 'Campus Excellence Awards 2026',
        'status' => ProgramStatus::Published,
        'ends_at' => now()->addDays(9)->addHours(4)->addMinutes(10),
    ]);
    AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create(['name' => 'Artiste of the Year']);
    $revealed = AwardCategory::factory()
        ->for($program)
        ->status(CategoryStatus::Revealed)
        ->create(['name' => 'Song of the Year']);
    withLedgerVotes(Nominee::factory()->for($revealed)->create(['name' => 'Kweku Bentil', 'stage_name' => null]), 40);
    withLedgerVotes(Nominee::factory()->for($revealed)->create(['name' => 'Esi Mensah', 'stage_name' => null]), 12);
});

it('shows each site page without accessibility issues at every width', function (string $path, string $name): void {
    foreach ([[360, 800], [768, 1024], [1440, 900]] as [$width, $height]) {
        // Under reduced motion nothing fades, so axe sees the whole page as visitors finally do.
        visit($path, ['reducedMotion' => 'reduce'])
            ->resize($width, $height)
            ->assertNoJavaScriptErrors()
            ->assertSourceHas('/build/assets/home-')
            ->assertSourceMissing('livewire')
            ->assertNoAccessibilityIssues(1)
            ->screenshot(fullPage: true, filename: "site-{$name}-{$width}");
    }
})->with([
    'homepage' => ['/', 'home'],
    'results' => ['/results', 'results'],
    'organizers' => ['/organizers', 'organizers'],
    'request access' => ['/organizers/request-access', 'request-access'],
    'help' => ['/help', 'help'],
    'contact' => ['/contact', 'contact'],
    'terms' => ['/terms', 'terms'],
    'privacy' => ['/privacy', 'privacy'],
    'refunds' => ['/refunds', 'refunds'],
])->group('advisory');

it('fits the headline, the search and the start of Live now on a 360 px phone, with motion', function (): void {
    $page = waitUntil(visit('/')->resize(360, 800), SITE_SECTIONS_SHOWN);

    $page->assertNoAccessibilityIssues(1)->assertSourceHas('Closes in 9d 4h');
    expect($page->script('() => document.querySelector("#live-heading").getBoundingClientRect().top < innerHeight'))
        ->toBeTrue()
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))
        ->toBeTrue();
})->group('critical');

it('keeps the header transparent over the hero and makes it solid once scrolled past', function (): void {
    $page = visit('/')->resize(1440, 900);
    $solid = '() => document.querySelector("[data-site-header]").hasAttribute("data-scrolled")';

    expect($page->script($solid))->toBeFalse();
    $page->script('() => window.scrollTo(0, document.querySelector("[data-hero]").offsetHeight + 200)');
    expect(waitUntil($page, 'document.querySelector("[data-site-header]").hasAttribute("data-scrolled")')->script(
        $solid,
    ))->toBeTrue();
})->group('advisory');

it('finds a nominee by code from the homepage, without JavaScript help', function (): void {
    $nominee = Nominee::query()->where('name', 'Kweku Bentil')->sole();

    visit('/')
        ->resize(360, 800)
        ->type('#find-nominee', $nominee->nominee_code)
        ->press('Find')
        ->assertPathIs(
            "/{$nominee->awardProgram->organization->slug}/{$nominee->awardProgram->slug}/{$nominee->nominee_code}",
        );
})->group('critical');
