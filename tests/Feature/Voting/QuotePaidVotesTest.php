<?php

declare(strict_types=1);

use App\Actions\Voting\InitiatePaidVoteOrderAction;
use App\Actions\Voting\QuotePaidVotesAction;
use App\Enums\TransactionStatus;
use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteTransaction;

beforeEach(function (): void {
    config(['voting.paystack_fee_bps' => 195]);
    $this->gateway = fakeGateway();
});

it('shows the voter the exact numbers that are stored and sent to Paystack', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->state(['price_per_vote' => 150]))->create();

    $quote = app(QuotePaidVotesAction::class)->execute($nominee, 17);
    app(InitiatePaidVoteOrderAction::class)->execute($nominee, 17);

    $transaction = VoteTransaction::query()->sole();

    expect($quote->subtotal)
        ->toBe(2_550)
        ->and($quote->subtotal + $quote->processingFee)
        ->toBe($quote->total)
        ->and($transaction->net_amount)
        ->toBe($quote->subtotal)
        ->and($transaction->processing_fee_pesewas)
        ->toBe($quote->processingFee)
        ->and($transaction->gross_amount)
        ->toBe($quote->total)
        ->and($this->gateway->initialized[0]->amount)
        ->toBe($quote->total);
});

it('gives the organizer exactly the vote price and the voter pays the fee on top', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->state(['price_per_vote' => 100]))->create();

    $quote = app(QuotePaidVotesAction::class)->execute($nominee, 25);

    expect($quote->subtotal)
        ->toBe(25 * 100) // organizer amount = vote price
        ->and($quote->processingFee)
        ->toBe(50) // Paystack fee, covered by the voter
        ->and($quote->total)
        ->toBe(2_550) // ceil(2500 / 0.9805)
        ->and($quote->subtotal + $quote->processingFee)
        ->toBe($quote->total);
});

it('uses the fee rate from config', function (): void {
    config(['voting.paystack_fee_bps' => 150]);
    $nominee = Nominee::factory()->for(AwardCategory::factory()->state(['price_per_vote' => 10_000]))->create();

    expect(app(QuotePaidVotesAction::class)->execute($nominee, 1)->total)->toBe(10_153); // ceil(10000 / 0.985)
});

it('blocks paid votes for an organization without a Paystack subaccount, with an organizer-facing message', function (): void {
    $program = AwardProgram::factory()->for(Organization::factory()->withoutPaystackSubaccount())->create();
    $nominee = Nominee::factory()->for(AwardCategory::factory()->for($program))->create();

    expect(fn() => app(InitiatePaidVoteOrderAction::class)->execute($nominee, 5))
        ->toThrow(CheckoutUnavailableException::class, 'Paystack subaccount');

    expect(VoteTransaction::query()->count())->toBe(0)->and($this->gateway->initialized)->toBeEmpty();
});

it('settles to the organization\'s subaccount and snapshots it on the transaction', function (): void {
    $program = AwardProgram::factory()->for(Organization::factory()->state([
        'paystack_subaccount_code' => 'ACCT_campus01',
    ]))->create();
    $nominee = Nominee::factory()->for(AwardCategory::factory()->for($program))->create();

    app(InitiatePaidVoteOrderAction::class)->execute($nominee, 2);
    $program->organization->forceFill(['paystack_subaccount_code' => 'ACCT_changedlater'])->save();

    expect($this->gateway->initialized[0]->subaccountCode)
        ->toBe('ACCT_campus01')
        ->and(VoteTransaction::query()->sole()->settlement_subaccount_code)
        ->toBe('ACCT_campus01')
        ->and(VoteTransaction::query()->sole()->status)
        ->toBe(TransactionStatus::Pending);
});
