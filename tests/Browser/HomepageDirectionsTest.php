<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Support\Dev\HomepageDirections;

/*
 * The three homepage directions (Step A of the homepage brief) in a real browser: plain JavaScript only, axe clean at
 * every impact level, and on a 360 x 800 phone the headline, the search and the start of "Live now" fit the first
 * screen. Advisory: dev-only pages.
 */

// Sections fade up as they enter (resources/js/home.js): measure the finished page, never a half-faded one.
const SECTIONS_SHOWN =
    '[...document.querySelectorAll("[data-reveal]")].every('
    . '(s) => s.hasAttribute("data-revealed") && getComputedStyle(s).opacity === "1")';

beforeEach(function (): void {
    app()->detectEnvironment(static fn(): string => 'local');
    $program = AwardProgram::factory()->create([
        'title' => 'Campus Excellence Awards 2026',
        'status' => ProgramStatus::Published,
        'ends_at' => now()->addDays(9)->addHours(4)->addMinutes(10), // a margin: the browser's clock runs later
    ]);
    AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create();
});

it('fits the first screen of a 360 px phone and has no accessibility issues', function (string $direction): void {
    $page = waitUntil(visit("/dev/homepage/{$direction}")->resize(360, 800), SECTIONS_SHOWN);

    $page
        ->assertNoJavaScriptErrors()
        ->assertSourceHas('/build/assets/home-')
        ->assertSourceMissing('livewire')
        ->assertSourceHas('Closes in 9d 4h') // below the first screen on a phone, by design
        ->assertNoAccessibilityIssues(1)
        ->screenshot(filename: "homepage-{$direction}-360");

    expect($page->script('() => document.querySelector("#live-heading").getBoundingClientRect().top < innerHeight'))
        ->toBeTrue()
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))
        ->toBeTrue();
})
    ->with(HomepageDirections::keys())
    ->group('advisory');

it('has no accessibility issues on desktop, including the empty state', function (string $direction): void {
    waitUntil(visit("/dev/homepage/{$direction}")->resize(1440, 900), SECTIONS_SHOWN)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1)
        ->screenshot(filename: "homepage-{$direction}-1440");

    // Under reduced motion nothing fades: the page is complete at once.
    visit("/dev/homepage/{$direction}?empty=1", [
        'reducedMotion' => 'reduce',
    ])->resize(360, 800)->assertSee('No awards are open for voting right now.')->assertNoAccessibilityIssues(1);
})
    ->with(HomepageDirections::keys())
    ->group('advisory');
