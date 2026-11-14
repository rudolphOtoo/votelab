<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Http\Controllers\Dev\HomepageDirectionController;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Support\Design\Contrast;
use App\Support\Dev\HomepageDirections;
use Carbon\CarbonImmutable;

/*
 * /dev/homepage/{a|b|c}: the three homepage directions of Step A of the homepage brief. Local only, read only, and
 * never a vote count: only programs, organizations, status and closing times.
 */

function liveProgram(string $title, CategoryStatus $status = CategoryStatus::Open): AwardProgram
{
    $program = AwardProgram::factory()->create([
        'title' => $title,
        'status' => ProgramStatus::Published,
        'ends_at' => now()->addDays(2)->addHours(4)->addMinutes(10),
    ]);
    $category = AwardCategory::factory()->for($program)->status($status)->create();
    Nominee::factory()->for($category)->create()->forceFill(['votes_count' => 987_654])->save();

    return $program;
}

it('does not exist outside APP_ENV=local', function (string $environment): void {
    app()->detectEnvironment(static fn(): string => $environment);

    $this->get('/dev/homepage/a')->assertNotFound();
})->with(['production', 'staging', 'testing']);

it('shows each direction with the programs open for voting, never a vote count', function (string $direction): void {
    app()->detectEnvironment(static fn(): string => 'local');
    liveProgram('Campus Awards');
    liveProgram('Hidden Results Awards', CategoryStatus::Embargoed);
    liveProgram('Finished Awards', CategoryStatus::Revealed);
    liveProgram('Closed Awards', CategoryStatus::Closed);
    AwardProgram::factory()->create(['title' => 'Draft Awards', 'status' => ProgramStatus::Draft]);

    $this
        ->get("/dev/homepage/{$direction}")
        ->assertOk()
        ->assertSee(HomepageDirections::find($direction)['name'] ?? '')
        ->assertSee('Enter a nominee code, e.g. AM27, or a name')
        ->assertSee('Campus Awards')
        ->assertSee('Hidden Results Awards') // still taking votes; its results are never shown here anyway
        ->assertSee('Closes in 2d 4h')
        ->assertDontSee('Finished Awards')
        ->assertDontSee('Closed Awards')
        ->assertDontSee('Draft Awards')
        ->assertDontSee('987,654')
        ->assertDontSee('987654')
        ->assertHeader('X-Robots-Tag', 'noindex');

    // No Livewire on these pages is checked in tests/Browser/HomepageDirectionsTest.php: in a feature run Livewire
    // injects its script into any page once an earlier test in the same process has rendered a component.
})->with(HomepageDirections::keys());

it('shows a friendly empty state when nothing is open', function (string $direction): void {
    app()->detectEnvironment(static fn(): string => 'local');

    $this->get("/dev/homepage/{$direction}")->assertOk()->assertSee('No awards are open for voting right now.');
})->with(HomepageDirections::keys());

it('is a 404 for a direction that does not exist', function (): void {
    app()->detectEnvironment(static fn(): string => 'local');

    $this->get('/dev/homepage/d')->assertNotFound();
});

it('meets WCAG 2.2 AA for every colour pair of every direction', function (string $direction): void {
    $failures = array_filter(
        HomepageDirections::pairs($direction),
        static fn(array $pair): bool => $pair['ratio'] < $pair['minimum'],
    );

    expect(array_column($failures, 'usage'))->toBeEmpty()->and(HomepageDirections::pairs($direction))->not->toBeEmpty();
})->with(HomepageDirections::keys());

it('words the countdown like resources/js/home.js does', function (int $minutes, string $text): void {
    $now = CarbonImmutable::parse('2026-11-14 12:00:00');

    expect(HomepageDirectionController::closesIn($now->addMinutes($minutes), $now))->toBe($text);
})->with([
    [-5, 'Closing now'],
    [0, 'Closing now'],
    [9, 'Closes in 9m'],
    [192, 'Closes in 3h 12m'],
    [(2 * 1440) + 245, 'Closes in 2d 4h'],
]);

it('uses only colours it measures', function (): void {
    foreach (HomepageDirections::keys() as $direction) {
        foreach (HomepageDirections::find($direction)['colors'] ?? [] as $name => $hex) {
            expect(Contrast::isHex($hex))->toBeTrue("{$direction}: {$name}");
        }
    }
});
