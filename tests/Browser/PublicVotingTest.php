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

// Required CI group (D-113): the voter's path through real Chromium at phone width, hidden results, and axe
// (every impact level) on the main public pages. The Step 2 redesign must keep all of these green.

/** Distinctive counts, so any appearance on a page is detectable. */
const BROWSER_LEAK_COUNTS = [987_654, 123_457];

function browserCategory(CategoryStatus $status, VotingMode $mode = VotingMode::Paid): AwardCategory
{
    $organization = Organization::factory()->create(['slug' => 'campus', 'name' => 'Campus Creatives']);
    $program = AwardProgram::factory()->for($organization)->create([
        'slug' => 'awards',
        'title' => 'Campus Awards 2026',
    ]);
    $category = AwardCategory::factory()
        ->for($program)
        ->status($status)
        ->mode($mode)
        ->create(['slug' => 'artiste', 'name' => 'Artiste of the Year', 'price_per_vote' => 100]);

    foreach (BROWSER_LEAK_COUNTS as $i => $votes) {
        $created = Nominee::factory()->for($category)->create([
            'nominee_code' => "AM2{$i}",
            'name' => $i === 0 ? 'Akosua Mensah' : 'Kwame Asante',
        ]);
        withLedgerVotes($created, $votes);
    }

    cacheLeaderboardCopy($category->id);

    return $category;
}

it('takes a voter from the nominee link to checkout on a 360 px phone', function (): void {
    $gateway = fakeGateway();
    browserCategory(CategoryStatus::Open);

    visit('/campus/awards/AM20')
        ->resize(360, 800)
        ->assertSee('Akosua Mensah')
        ->assertSee('Number of votes')
        ->click('[aria-label="One more vote"]')
        ->assertValue('#vote-quantity', '2')
        ->assertNoJavaScriptErrors()
        ->click('button[type="submit"]')
        ->wait(1.5);

    $transaction = VoteTransaction::query()->sole();

    expect($transaction->status)
        ->toBe(TransactionStatus::Pending)
        ->and($transaction->quantity)
        ->toBe(2)
        ->and($gateway->initialized)
        ->toHaveCount(1);
})->group('critical');

it('never shows vote counts while results are hidden, including after a live refresh', function (CategoryStatus $status): void {
    browserCategory($status);

    foreach (['/campus/awards/categories/artiste', '/campus/awards/AM20', '/campus/awards'] as $path) {
        $page = visit($path)->resize(360, 800)->wait(3.5); // past the 3 s results poll

        foreach (BROWSER_LEAK_COUNTS as $votes) {
            $page
                ->assertDontSee((string) $votes)
                ->assertDontSee(number_format($votes))
                ->assertSourceMissing((string) $votes)
                ->assertSourceMissing(number_format($votes));
        }
    }
})->with([
    'embargoed' => CategoryStatus::Embargoed,
    'closed' => CategoryStatus::Closed,
])->group('critical');

it('has no accessibility issues on the main public pages', function (string $path): void {
    browserCategory(CategoryStatus::Open);

    visit($path)
        ->resize(360, 800)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        // D-124: voters get the public bundle only (no passkeys, crop editor or stage code).
        ->assertSourceHas('/build/assets/public-')
        ->assertSourceMissing('/build/assets/passkeys-')
        ->assertSourceMissing('/build/assets/stage-');
})->with([
    'program landing' => '/campus/awards',
    'category' => '/campus/awards/categories/artiste',
    'nominee deep link (vote drawer open)' => '/campus/awards/AM20',
])->group('critical');

it('has no accessibility issues on the payment result pages', function (TransactionStatus $status): void {
    fakeGateway(); // unknown references can't be verified, so a pending payment stays pending
    $nominee = browserCategory(CategoryStatus::Open)->nominees()->firstOrFail();
    VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => 'VL_BROWSER_RESULT',
        'status' => $status,
        'quantity' => 10,
        'credited_at' => $status === TransactionStatus::Successful ? now() : null,
        'verified_at' => $status === TransactionStatus::Pending ? null : now(),
    ]);

    visit('/payments/callback?reference=VL_BROWSER_RESULT')
        ->resize(360, 800)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        ->screenshot(filename: 'payment-' . $status->value . '-360');
})->with([
    'success' => TransactionStatus::Successful,
    'pending' => TransactionStatus::Pending,
    'failed' => TransactionStatus::Failed,
])->group('critical');
