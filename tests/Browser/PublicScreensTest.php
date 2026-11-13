<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteTransaction;

// Advisory group (D-113): every public screen and state at 360, 768 and 1440 px for review, each with axe at
// every impact level. Photos are inline SVG portraits so nothing leaves the test server.

function portrait(string $hue): string
{
    $svg =
        "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500'><rect width='400' height='500' fill='hsl({$hue},45%,82%)'/>"
        . "<circle cx='200' cy='190' r='86' fill='hsl({$hue},30%,42%)'/><rect x='70' y='300' width='260' height='260' rx='130' fill='hsl({$hue},30%,42%)'/></svg>";

    return 'data:image/svg+xml,' . rawurlencode($svg);
}

function showcaseProgram(): AwardProgram
{
    $organization = Organization::factory()->create(['slug' => 'campus', 'name' => 'Accra Campus Creatives']);
    $program = AwardProgram::factory()->for($organization)->create([
        'slug' => 'awards',
        'title' => 'Campus Excellence Awards 2026',
        'tagline' => 'Celebrating the students who made this year unforgettable.',
        'banner_url' => null,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->addDays(4)->setTime(20, 0),
    ]);

    $categories = [
        ['artiste', 'Artiste of the Year', CategoryStatus::Open, VotingMode::Paid],
        ['popular', 'Most Popular Student', CategoryStatus::Open, VotingMode::Hybrid],
        ['dancer', 'Best Dancer', CategoryStatus::Embargoed, VotingMode::Paid],
        ['song', 'Song of the Year', CategoryStatus::Revealed, VotingMode::Paid],
        ['newcomer', 'Best Newcomer of the Academic Year in Creative Arts', CategoryStatus::Closed, VotingMode::Paid],
    ];
    $names = [
        'Akosua Mensah-Boateng',
        'Kwabena Owusu-Ansah',
        'Efua Asantewaa',
        'Yaw Darko',
        'Nana Ama Serwaa Agyemang',
    ];
    $code = 10;

    foreach ($categories as $c => [$slug, $name, $status, $mode]) {
        $category = AwardCategory::factory()
            ->for($program)
            ->status($status)
            ->mode($mode)
            ->create([
                'slug' => $slug,
                'name' => $name,
                'price_per_vote' => 100,
                'free_vote_limit' => 1,
                'display_order' => $c,
            ]);

        foreach ($names as $i => $nominee) {
            $created = Nominee::factory()->for($category)->create([
                'name' => $nominee,
                'stage_name' => null,
                'nominee_code' => strtoupper(substr($slug, 0, 2)) . $code++,
                'avatar_url' => $i === 4 ? null : portrait((string) ((($c * 70) + ($i * 45)) % 360)),
                'display_order' => $i,
            ]);
            withLedgerVotes($created, [1_240, 3_912, 860, 2_105, 415][$i]);
        }

        cacheLeaderboardCopy($category->id);
    }

    return $program;
}

function paymentResult(TransactionStatus $status): string
{
    $nominee = Nominee::query()->where('nominee_code', 'AR10')->sole();
    VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => 'VL_7K2M9Q4XZP',
        'status' => $status,
        'quantity' => 20,
        'credited_at' => $status === TransactionStatus::Successful ? now() : null,
        'verified_at' => $status === TransactionStatus::Pending ? null : now(),
    ]);

    return '/payments/callback?reference=VL_7K2M9Q4XZP';
}

it('renders every public screen accessibly at each width', function (string $screen, int $width, int $height): void {
    fakeGateway(); // unknown references can't be verified, so a pending payment stays pending
    showcaseProgram();

    $path = match ($screen) {
        'landing' => '/campus/awards',
        'landing-search' => '/campus/awards?q=Ansah',
        'category-live' => '/campus/awards/categories/artiste',
        'category-hidden' => '/campus/awards/categories/dancer',
        'category-closed' => '/campus/awards/categories/newcomer',
        'category-winner' => '/campus/awards/categories/song',
        'drawer-paid' => '/campus/awards/AR10',
        'drawer-free' => '/campus/awards/PO16',
        'payment-success' => paymentResult(TransactionStatus::Successful),
        'payment-pending' => paymentResult(TransactionStatus::Pending),
        'payment-failed' => paymentResult(TransactionStatus::Failed),
    };

    $page = visit($path)->resize($width, $height);

    if ($screen === 'drawer-free') {
        $page->click('Free vote');
    }

    waitUntilSettled($page)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        ->screenshot(fullPage: !str_starts_with($screen, 'drawer'), filename: "public-{$screen}-{$width}");
})
    ->with([
        'landing',
        'landing-search',
        'category-live',
        'category-hidden',
        'category-closed',
        'category-winner',
        'drawer-paid',
        'drawer-free',
        'payment-success',
        'payment-pending',
        'payment-failed',
    ])
    ->with([
        'phone' => [360, 800],
        'tablet' => [768, 1024],
        'desktop' => [1440, 900],
    ])
    ->group('advisory', 'screenshots');

it('returns focus to the Vote button when the drawer closes', function (): void {
    fakeGateway();
    showcaseProgram();

    $voteButton = 'vote-' . Nominee::query()->where('nominee_code', 'AR11')->value('id');

    $page = visit('/campus/awards/categories/artiste')
        ->resize(360, 800)
        ->click('#' . $voteButton)
        ->assertSee('Vote for Kwabena Owusu-Ansah')
        ->keys('#vote-quantity', 'Escape');

    waitUntil($page, 'document.activeElement?.id === ' . json_encode($voteButton))->assertScript(
        'document.activeElement?.id',
        $voteButton,
    );
})->group('advisory');

it('serves each layout the smallest portrait that stays sharp', function (): void {
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::fake('local');
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create(['slug' => 'artiste']);
    $owner = organizer(App\Enums\OrganizationRole::Owner, $category->organization);
    $nominee = app(App\Actions\Admin\CreateNomineeAction::class)->execute($category, $owner, [
        'name' => 'Ama Mensah',
        'photo' => Illuminate\Http\UploadedFile::fake()->image('ama.jpg', 1000, 1250),
    ]);
    $program = $category->awardProgram;
    $cards = route('public.category', [$program->organization, $program, $category], absolute: false);
    $drawer = route('public.nominee', [$program->organization, $program, $nominee->nominee_code], absolute: false);
    $card = '() => document.querySelector("main ul img[srcset]").currentSrc';
    $cardLoaded =
        'document.querySelector("main ul img[srcset]")?.complete'
        . ' && document.querySelector("main ul img[srcset]").currentSrc !== ""';

    // Viewport first, then the page: browsers keep an already-chosen larger image after a resize. The test
    // browser renders at 2 device pixels per CSS pixel, like most phones.
    $phone = waitUntil(visit('/up')->resize(360, 800)->navigate($cards), $cardLoaded);
    expect($phone->script('() => window.devicePixelRatio'))->toBe(2);

    // A 156 px card needs 312 device pixels: the 400 px copy, never the 800 px portrait.
    expect($phone->script($card))->toEndWith('-400w.webp');

    // The 56 px drawer thumbnail needs 112: the 240 px copy.
    $thumb = waitUntil(
        visit('/up')->resize(360, 800)->navigate($drawer),
        'document.querySelector("[role=dialog] img[srcset]")?.complete'
        . ' && document.querySelector("[role=dialog] img[srcset]").currentSrc !== ""',
    );
    expect($thumb->script('() => document.querySelector("[role=dialog] img[srcset]").currentSrc'))
        ->toEndWith('-240w.webp');

    // A 224 px desktop card needs 448: the full portrait.
    $desktop = waitUntil(visit('/up')->resize(1440, 900)->navigate($cards), $cardLoaded);
    expect($desktop->script($card))->not->toContain('-400w')->not->toContain('-240w');
})->group('critical');
