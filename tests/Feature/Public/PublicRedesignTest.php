<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Livewire\Public\VoteDrawer;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteTransaction;
use App\Support\LeaderboardCache;
use App\Support\PublicSchedule;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function redesignCategory(
    CategoryStatus $status,
    VotingMode $mode = VotingMode::Paid,
    array $votes = [10, 30, 20],
): AwardCategory {
    $organization = Organization::factory()->create(['slug' => 'campus', 'settings' => ['brand_color' => '#8b1e3f']]);
    $program = AwardProgram::factory()->for($organization)->create([
        'slug' => 'awards',
        'title' => 'Campus Awards 2026',
    ]);
    $category = AwardCategory::factory()
        ->for($program)
        ->status($status)
        ->mode($mode)
        ->create([
            'slug' => 'artiste',
            'name' => 'Artiste of the Year',
            'price_per_vote' => 100,
            'free_vote_limit' => 2,
        ]);

    foreach (['Ama First', 'Kojo Second', 'Esi Third'] as $i => $name) {
        Nominee::factory()
            ->for($category)
            ->create(['name' => $name, 'stage_name' => null, 'nominee_code' => 'NM' . ($i + 10), 'display_order' => $i])
            ->forceFill(['votes_count' => $votes[$i]])
            ->save();
    }

    LeaderboardCache::refresh($category->id);

    return $category;
}

it('keeps display order while voting is open and orders by votes once the winner is announced', function (): void {
    redesignCategory(CategoryStatus::Open);

    $this->get('/campus/awards/categories/artiste')->assertSeeInOrder([
        'Ama First',
        'Kojo Second',
        'Esi Third',
    ])->assertDontSee('Winner</span>', escape: false);

    AwardCategory::query()->update(['status' => CategoryStatus::Revealed]);
    LeaderboardCache::refresh(AwardCategory::query()->sole()->id);

    $this
        ->get('/campus/awards/categories/artiste')
        ->assertSeeInOrder(['Kojo Second', 'Esi Third', 'Ama First'])
        ->assertSee('Winner');
});

it('tells voters results are hidden, in plain words', function (CategoryStatus $status, string $note): void {
    redesignCategory($status);

    $this->get('/campus/awards/categories/artiste')->assertSee($note)->assertDontSee('mbargo');
})->with([
    'hidden' => [CategoryStatus::Embargoed, 'Results are hidden until the reveal. Your votes still count.'],
    'closed' => [CategoryStatus::Closed, 'Voting has closed. Results are hidden until the reveal.'],
]);

it('shows the nominee code prominently with a copy button', function (): void {
    redesignCategory(CategoryStatus::Open);

    $this->get('/campus/awards/categories/artiste')->assertSee('aria-label="Copy code NM10"', escape: false);
});

it('offers quick picks that set the quantity, through the clamped property', function (): void {
    fakeGateway();
    $nominee = redesignCategory(CategoryStatus::Open)->nominees()->firstOrFail();

    Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->assertSeeInOrder(['1', '5', '10', '20', '50'])
        ->set('quantity', 20)
        ->assertSet('quantity', 20)
        ->assertSee('aria-pressed="true"', escape: false)
        ->assertSee('Pay GHS 20.40 securely with Paystack')
        ->assertSee('Mobile money (MTN, Telecel, AirtelTigo) or card');
});

it('shows the free votes left today, and none once they are used', function (): void {
    fakeGateway();
    $nominee = redesignCategory(CategoryStatus::Open, VotingMode::Free)->nominees()->firstOrFail();

    $drawer = Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])->assertSee(
        'You have 2 free votes left today in this category.',
    );

    $drawer->call('castFreeVote')->assertSee('You have 1 free vote left today in this category.');
    $drawer->call('castFreeVote')->assertSee("You've used today's free votes in this category.", escape: false);
});

it('gives each payment result its own state, actions and copyable reference', function (
    TransactionStatus $status,
    array $see,
    array $dontSee,
): void {
    fakeGateway();
    $nominee = redesignCategory(CategoryStatus::Open)->nominees()->firstOrFail();
    VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => 'VL_RESULT_1',
        'status' => $status,
        'quantity' => 5,
        'credited_at' => $status === TransactionStatus::Successful ? now() : null,
        'verified_at' => $status === TransactionStatus::Pending ? null : now(),
    ]);

    $page = $this
        ->get('/payments/callback?reference=VL_RESULT_1')
        ->assertOk()
        ->assertSee('data-payment-state="' . $status->value . '"', escape: false)
        ->assertSee('VL_RESULT_1')
        ->assertSee('aria-label="Copy reference"', escape: false)
        ->assertSee('<meta name="robots" content="noindex">', escape: false)
        ->assertSee('--brand: #8b1e3f;', escape: false);

    foreach ($see as $text) {
        $page->assertSee($text);
    }

    foreach ($dontSee as $text) {
        $page->assertDontSee($text);
    }
})->with([
    'success' => [
        TransactionStatus::Successful,
        ['Your votes are in', 'Share that you voted', 'https://wa.me/?text=', 'Vote again'],
        ['Checking again', 'Try again'],
    ],
    'pending' => [
        TransactionStatus::Pending,
        [
            'Confirming your payment',
            'Waiting for mobile money approval',
            'Checking again in',
            'Check now',
            'Pause checks',
        ],
        ['Share that you voted'],
    ],
    'failed' => [
        TransactionStatus::Failed,
        ['Payment not completed', 'Try again'],
        ['Share that you voted', 'Checking again'],
    ],
]);

it('describes every public page for search and link previews', function (
    string $path,
    string $description,
    string $canonical,
): void {
    redesignCategory(CategoryStatus::Open);

    $this
        ->get($path)
        ->assertOk()
        ->assertSee('<meta name="description" content="' . e($description), escape: false)
        ->assertSee('<link rel="canonical" href="' . url($canonical) . '">', escape: false)
        ->assertSee('--brand: #8b1e3f;', escape: false);
})->with([
    'landing' => ['/campus/awards', 'Vote in Campus Awards 2026 by', '/campus/awards'],
    'category' => [
        '/campus/awards/categories/artiste',
        'See the nominees for Artiste of the Year at Campus Awards 2026',
        '/campus/awards/categories/artiste',
    ],
    'deep link' => [
        '/campus/awards/NM10',
        'Vote for Ama First (code NM10) in Artiste of the Year',
        '/campus/awards/NM10',
    ],
]);

it('states when voting opens or closes, in Accra time', function (): void {
    $now = CarbonImmutable::parse('2026-12-10 12:00', 'UTC');
    $program = AwardProgram::factory()->make([
        'starts_at' => $now->addDays(2),
        'ends_at' => CarbonImmutable::parse('2026-12-14 20:00', 'UTC'),
    ]);
    $category = static fn(CategoryStatus $status): AwardCategory => AwardCategory::factory()->status($status)->make();

    expect(PublicSchedule::programStatus($program, collect([$category(CategoryStatus::Open)]), $now))
        ->toBe(['label' => 'Voting open — closes Mon 14 Dec, 8:00 PM', 'tone' => 'success'])
        ->and(PublicSchedule::programStatus($program, collect(), $now)['label'])
        ->toBe('Voting opens Sat 12 Dec, 12:00 PM')
        ->and(
            PublicSchedule::programStatus(
                $program,
                collect([$category(CategoryStatus::Revealed)]),
                $now->addDays(10),
            )['label'],
        )
        ->toBe('Winners announced')
        ->and(
            PublicSchedule::programStatus(
                $program,
                collect([$category(CategoryStatus::Closed)]),
                $now->addDays(10),
            )['label'],
        )
        ->toBe('Voting closed');
});

it('never sends the payment reference on: no referrer, and the share links to the nominee page only', function (): void {
    fakeGateway();
    $nominee = redesignCategory(CategoryStatus::Open)->nominees()->firstOrFail();
    VoteTransaction::factory()->for($nominee)->create([
        'payment_reference' => 'VL_PRIVATE_REF9',
        'status' => TransactionStatus::Successful,
        'quantity' => 5,
        'credited_at' => now(),
        'verified_at' => now(),
    ]);

    $response = $this->get('/payments/callback?reference=VL_PRIVATE_REF9')->assertOk()->assertHeader(
        'Referrer-Policy',
        'no-referrer',
    );

    preg_match('#href="https://wa\.me/\?text=([^"]+)"#', (string) $response->getContent(), $match);
    $shared = rawurldecode(html_entity_decode($match[1] ?? ''));

    expect($shared)
        ->toContain(url('/campus/awards/NM10'))
        ->not->toContain('VL_PRIVATE_REF9')
        ->not->toContain('reference')
        ->not->toContain('payments/callback');
});

it('never promises a VoteLab receipt email: Paystack may send one, and the reference is on the result page', function (): void {
    fakeGateway();
    $nominee = redesignCategory(CategoryStatus::Open)->nominees()->firstOrFail();

    Livewire::test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->assertSee('Email for a payment receipt')
        ->assertSee('Paystack may email you a receipt for this payment.')
        ->assertSee('Your payment reference is shown on the next page either way.')
        ->assertDontSee('email your receipt');
});
