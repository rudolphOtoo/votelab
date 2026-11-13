<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;

// Stage screen (UI Step 4): every scene at 1920×1080 and 1280×720 with axe at every impact level, the reveal
// sequence, and reduced motion. Advisory, except the reduced-motion check. Organization colour #8B1E3F, an
// inline SVG logo and portraits, so nothing leaves the test server.

function stageArt(int $hue, string $shape = 'portrait'): string
{
    $svg = $shape === 'logo'
        ? "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'><circle cx='60' cy='60' r='56' fill='hsl({$hue},55%,45%)'/><text x='60' y='78' font-size='52' font-family='sans-serif' font-weight='700' text-anchor='middle' fill='white'>AC</text></svg>"
        : "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500'><rect width='400' height='500' fill='hsl({$hue},40%,30%)'/><circle cx='200' cy='190' r='86' fill='hsl({$hue},35%,62%)'/><rect x='70' y='300' width='260' height='260' rx='130' fill='hsl({$hue},35%,62%)'/></svg>";

    return 'data:image/svg+xml,' . rawurlencode($svg);
}

function stageShowcase(): AwardProgram
{
    $organization = Organization::factory()->create([
        'name' => 'Accra Campus Creatives',
        'slug' => 'campus',
        'logo_url' => stageArt(345, 'logo'),
        'settings' => ['brand_color' => '#8b1e3f'],
    ]);
    $program = AwardProgram::factory()->for($organization)->create([
        'title' => 'Campus Excellence Awards 2026',
        'slug' => 'awards',
    ]);
    $names = [
        'Akosua Mensah-Boateng',
        'Kwabena Owusu-Ansah',
        'Efua Asantewaa',
        'Yaw Darko',
        'Nana Ama Serwaa Agyemang',
        'Kofi Boakye',
        'Abena Pokuaa',
        'Kwame Nkansah',
    ];
    $scenes = [
        ['live', 'Artiste of the Year', CategoryStatus::Open, [3912, 2105, 1240, 980, 860, 415, 230, 96]],
        ['hidden', 'Best Dancer', CategoryStatus::Embargoed, [500, 400, 300]],
        ['closed', 'Best Newcomer', CategoryStatus::Closed, [500, 400, 300]],
        ['winner', 'Song of the Year', CategoryStatus::Revealed, [3912, 2105, 1240]],
        ['tie', 'Most Popular Student', CategoryStatus::Revealed, [1500, 1500, 640]],
    ];

    foreach ($scenes as $c => [$slug, $name, $status, $votes]) {
        $category = AwardCategory::factory()
            ->for($program)
            ->status($status)
            ->create(['slug' => $slug, 'name' => $name, 'display_order' => $c]);

        foreach ($votes as $i => $count) {
            $nominee = Nominee::factory()->for($category)->create([
                'name' => $names[$i],
                'stage_name' => null,
                'avatar_url' => stageArt((($c * 60) + ($i * 40)) % 360),
                'display_order' => $i,
            ]);
            withLedgerVotes($nominee, $count);
        }

        cacheLeaderboardCopy($category->id);
    }

    return $program;
}

it('renders every stage scene accessibly on 1080p and 720p projectors', function (
    string $scene,
    int $width,
    int $height,
): void {
    if ($scene === 'empty') {
        AwardProgram::factory()->for(Organization::factory()->create([
            'slug' => 'campus',
            'settings' => ['brand_color' => '#8b1e3f'],
        ]))->create(['slug' => 'awards', 'title' => 'Campus Excellence Awards 2026']);
    } else {
        stageShowcase();
    }

    $path = match ($scene) {
        'empty' => '/stage/campus/awards',
        'intro' => '/stage/campus/awards?category=winner',
        default => "/stage/campus/awards?category={$scene}",
    };
    // The podium appears 1.8 s after "And the winner is…"; the intro is caught before that.
    $page = visit($path)->resize($width, $height);

    if (in_array($scene, ['winner', 'tie'], true)) {
        waitUntil($page, 'document.querySelector("[data-stage-winner]")?.checkVisibility()');
    }

    waitUntilSettled($page);

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        // Fits the projector: nothing below the fold on a 16:9 screen.
        ->assertScript('document.documentElement.scrollHeight <= window.innerHeight + 1', true)
        ->screenshot(fullPage: false, filename: "stage-{$scene}-{$width}");
})
    ->with(['empty', 'live', 'hidden', 'closed', 'intro', 'winner', 'tie'])
    ->with([
        '1080p' => [1920, 1080],
        '720p' => [1280, 720],
    ])
    ->group('advisory', 'screenshots');

it('builds the reveal: a pause, then the podium with confetti', function (): void {
    stageShowcase();

    $page = visit('/stage/campus/awards?category=winner')->resize(1920, 1080);
    waitUntil($page, 'document.body.innerText.includes("And the winner is…")')->assertScript(
        'document.querySelector("[data-stage-winner]")?.checkVisibility()',
        false,
    );

    waitUntil(
        $page,
        'document.querySelector("[data-stage-winner]")?.checkVisibility()'
        . ' && document.querySelector("[data-stage-confetti]").dataset.fired === "true"',
    );
})->group('advisory');

it('shows the podium at once and no confetti under reduced motion', function (): void {
    stageShowcase();

    $page = visit('/stage/campus/awards?category=winner', ['reducedMotion' => 'reduce'])->resize(1920, 1080);

    waitUntil($page, 'document.readyState === "complete"', 5)
        ->assertScript('document.querySelector("[data-stage-winner]")?.checkVisibility()', true)
        ->assertScript('getComputedStyle(document.querySelector("[data-stage-winner]")).animationName', 'none')
        // Proving confetti never fires needs waiting past the moment it would (1.8 s): the one fixed wait left.
        ->wait(2.5)
        ->assertScript('document.querySelector("[data-stage-confetti]").dataset.fired ?? "no"', 'no')
        ->assertScript('document.querySelectorAll("body > canvas").length', 0);
})->group('critical');

it('uses the organization colour and logo on stage, readable on basalt', function (): void {
    stageShowcase();

    visit('/stage/campus/awards?category=live')
        ->resize(1920, 1080)
        ->assertSourceHas('--stage-accent: ')
        ->assertScript(
            'getComputedStyle(document.querySelector("[data-stage-screen]")).getPropertyValue("--stage-accent").trim()',
            App\Support\Design\StageBranding::accent(Organization::query()->sole()),
        )
        ->assertScript('document.querySelector("header img")?.complete', true);
})->group('advisory');
