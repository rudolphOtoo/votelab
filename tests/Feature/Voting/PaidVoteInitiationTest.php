<?php

declare(strict_types=1);

use App\Actions\Voting\InitiatePaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteTransaction;

function paidNominee(
    CategoryStatus $status = CategoryStatus::Open,
    VotingMode $mode = VotingMode::Paid,
    int $price = 100,
): Nominee {
    $category = AwardCategory::factory()->status($status)->mode($mode)->create(['price_per_vote' => $price]);

    return Nominee::factory()->for($category)->create();
}

beforeEach(function (): void {
    config(['voting.paystack_fee_bps' => 195]);
    $this->gateway = fakeGateway();
});

it('snapshots gross, fee and net for 25 votes at GHS 1.00 on a pending transaction', function (): void {
    $nominee = paidNominee();

    $session = app(InitiatePaidVoteOrderAction::class)->execute($nominee, 25);

    $transaction = VoteTransaction::query()->sole();

    expect($transaction->status)
        ->toBe(TransactionStatus::Pending)
        ->and($transaction->quantity)
        ->toBe(25)
        ->and($transaction->unit_price)
        ->toBe(100)
        ->and($transaction->net_amount)
        ->toBe(2_500)
        ->and($transaction->processing_fee_pesewas)
        ->toBe(50)
        ->and($transaction->gross_amount)
        ->toBe(2_550)
        ->and($transaction->currency)
        ->toBe('GHS')
        ->and($transaction->nominee_id)
        ->toBe($nominee->id)
        ->and($transaction->award_category_id)
        ->toBe($nominee->award_category_id)
        ->and($transaction->award_program_id)
        ->toBe($nominee->award_program_id)
        ->and($transaction->organization_id)
        ->toBe($nominee->organization_id)
        ->and($session->reference)
        ->toBe($transaction->payment_reference)
        ->and($session->authorizationUrl)
        ->toBe("https://checkout.test/{$transaction->payment_reference}");

    $sent = $this->gateway->initialized[0];
    expect($sent->amount)
        ->toBe(2_550)
        ->and($sent->currency)
        ->toBe('GHS')
        ->and($sent->reference)
        ->toBe($transaction->payment_reference)
        ->and($sent->callbackUrl)
        ->toBe(route('payments.callback'))
        ->and($sent->metadata)
        ->toBe(['vote_transaction_id' => $transaction->id]);
});

it('gives every checkout a unique, prefixed reference', function (): void {
    $nominee = paidNominee();
    $action = app(InitiatePaidVoteOrderAction::class);

    $first = $action->execute($nominee, 1)->reference;
    $second = $action->execute($nominee, 1)->reference;

    expect($first)->not->toBe($second)->and($first)->toMatch('/^VL_[A-Z0-9]{26}$/');
});

it('sends a random proxy email and stores none when the voter gives no email', function (): void {
    config(['voting.proxy_email_domain' => 'voters.votelab.example']);

    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 1);
    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 1);

    [$first, $second] = $this->gateway->initialized;

    expect($first->email)
        ->toMatch('/^anon-[a-z0-9]{24}@voters\.votelab\.example$/')
        ->and($first->email)
        ->not
        ->toBe($second->email)
        ->and(VoteTransaction::query()->whereNotNull('voter_email')->count())
        ->toBe(0);
});

it('uses and keeps the voter\'s email when one is given', function (): void {
    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 1, '  Ama@Example.com ');

    expect($this->gateway->initialized[0]->email)
        ->toBe('ama@example.com')
        ->and(VoteTransaction::query()->sole()->voter_email)
        ->toBe('ama@example.com');
});

it('rejects an invalid email', function (): void {
    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 1, 'not-an-email');
})->throws(CheckoutUnavailableException::class);

it('only starts checkouts while the category accepts votes', function (CategoryStatus $status, bool $allowed): void {
    $nominee = paidNominee($status);

    try {
        app(InitiatePaidVoteOrderAction::class)->execute($nominee, 1);
        $started = true;
    } catch (CheckoutUnavailableException) {
        $started = false;
    }

    expect($started)
        ->toBe($allowed)
        ->and(VoteTransaction::query()->count())
        ->toBe($allowed ? 1 : 0)
        ->and($this->gateway->initialized)
        ->toHaveCount($allowed ? 1 : 0);
})->with([
    'draft' => [CategoryStatus::Draft, false],
    'open' => [CategoryStatus::Open, true],
    'embargoed' => [CategoryStatus::Embargoed, true],
    'closed' => [CategoryStatus::Closed, false],
    'revealed' => [CategoryStatus::Revealed, false],
]);

it('refuses paid votes in free-only categories and allows them in hybrid ones', function (): void {
    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(mode: VotingMode::Hybrid), 1);
    expect(VoteTransaction::query()->count())->toBe(1);

    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(mode: VotingMode::Free), 1);
})->throws(CheckoutUnavailableException::class);

it('refuses checkouts for unpublished programs', function (): void {
    $program = AwardProgram::factory()->draft()->create();
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create();

    app(InitiatePaidVoteOrderAction::class)->execute(Nominee::factory()->for($category)->create(), 1);
})->throws(CheckoutUnavailableException::class);

it('enforces the quantity limits', function (int $quantity): void {
    config(['voting.max_votes_per_order' => 500]);

    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), $quantity);
})->with([0, -5, 501])->throws(CheckoutUnavailableException::class);

it('accepts the maximum quantity', function (): void {
    config(['voting.max_votes_per_order' => 500]);

    app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 500);

    expect(VoteTransaction::query()->sole()->quantity)->toBe(500);
});

it('marks the transaction failed when Paystack will not start the checkout', function (): void {
    $this->gateway->failInitialization();

    expect(fn() => app(InitiatePaidVoteOrderAction::class)->execute(paidNominee(), 3))
        ->toThrow(CheckoutUnavailableException::class);

    $transaction = VoteTransaction::query()->sole();
    expect($transaction->status)
        ->toBe(TransactionStatus::Failed)
        ->and($transaction->status_reason)
        ->toBe('checkout_not_started');
});
