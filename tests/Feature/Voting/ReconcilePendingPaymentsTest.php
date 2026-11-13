<?php

declare(strict_types=1);

use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Actions\Voting\ReconcilePendingPaymentsAction;
use App\Enums\CategoryStatus;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\GatewayPaymentStatus;
use Illuminate\Support\Carbon;

/**
 * A pending order created $minutesAgo minutes before "now" (2026-10-30 20:00).
 */
function staleOrder(int $minutesAgo, int $quantity = 5, CategoryStatus $status = CategoryStatus::Open): VoteTransaction
{
    Carbon::setTestNow(Carbon::parse('2026-10-30 20:00:00')->subMinutes($minutesAgo));
    $category = AwardCategory::factory()->status($status)->create();
    $transaction = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create([
        'quantity' => $quantity,
    ]);
    test()->gateway->reports(
        $transaction->payment_reference,
        GatewayPaymentStatus::Pending,
        $transaction->gross_amount,
    );
    Carbon::setTestNow('2026-10-30 20:00:00');

    return $transaction;
}

function sweep(): App\Actions\Voting\ReconciliationSummary
{
    return app(ReconcilePendingPaymentsAction::class)->execute();
}

beforeEach(function (): void {
    config(['voting.sweeper' => ['verify_after_minutes' => 10, 'expire_after_hours' => 24, 'batch_size' => 500]]);
    $this->gateway = fakeGateway();
});

it('credits a paid order whose webhook never arrived, through the same crediting action', function (): void {
    $transaction = staleOrder(minutesAgo: 15, quantity: 6);
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    $summary = sweep();

    expect($summary->checked)
        ->toBe(1)
        ->and($summary->credited)
        ->toBe(1)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(6)
        ->and(VoteRecord::query()->sole()->vote_transaction_id)
        ->toBe($transaction->id);
});

it('leaves recent pending orders alone', function (): void {
    staleOrder(minutesAgo: 9);

    expect(sweep()->checked)->toBe(0)->and($this->gateway->verified)->toBeEmpty();
});

it('keeps a still-unpaid order pending inside the expiry window', function (): void {
    $transaction = staleOrder(minutesAgo: 60);

    $summary = sweep();

    expect($summary->stillPending)->toBe(1)->and($transaction->fresh()?->status)->toBe(TransactionStatus::Pending);
});

it('expires an order still unpaid after 24 hours', function (): void {
    $transaction = staleOrder(minutesAgo: (24 * 60) + 1);

    $summary = sweep();

    $transaction->refresh();
    expect($summary->expired)
        ->toBe(1)
        ->and($transaction->status)
        ->toBe(TransactionStatus::Expired)
        ->and($transaction->status_reason)
        ->toBe('unpaid_timeout')
        ->and($transaction->nominee->votes_count)
        ->toBe(0);
});

it('never expires an order it could not verify', function (): void {
    $transaction = staleOrder(minutesAgo: 3 * 24 * 60);
    $this->gateway->failVerification();

    $summary = sweep();

    expect($summary->errors)
        ->toBe(1)
        ->and($summary->expired)
        ->toBe(0)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Pending);
});

it('marks orders Paystack reports as failed', function (): void {
    $transaction = staleOrder(minutesAgo: 30);
    $this->gateway->reports($transaction->payment_reference, GatewayPaymentStatus::Failed, $transaction->gross_amount);

    expect(sweep()->settledWithoutCredit)
        ->toBe(1)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Failed);
});

it('does not sweep expired orders again', function (): void {
    $transaction = staleOrder(minutesAgo: (24 * 60) + 1);
    sweep();

    expect(sweep()->checked)->toBe(0)->and($transaction->fresh()?->status)->toBe(TransactionStatus::Expired);
});

it('still credits a late success on an expired order while voting is open', function (): void {
    $transaction = staleOrder(minutesAgo: (24 * 60) + 1, quantity: 8);
    sweep();
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    $result = app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, PaymentTrigger::Webhook);

    expect($result->newlyCredited)
        ->toBeTrue()
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(8);
});

it('records a late success on an expired order as paid_uncredited once voting has closed', function (): void {
    $transaction = staleOrder(minutesAgo: (24 * 60) + 1);
    sweep();
    $transaction
        ->awardCategory
        ->forceFill(['status' => CategoryStatus::Closed, 'closed_at' => now()->subHour()])
        ->save();
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, PaymentTrigger::Webhook);

    expect($transaction->fresh()?->status)
        ->toBe(TransactionStatus::PaidUncredited)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(0);
});

it('keeps an expired order expired when Paystack confirms it failed', function (): void {
    $transaction = staleOrder(minutesAgo: (24 * 60) + 1);
    sweep();
    $this->gateway->reports($transaction->payment_reference, GatewayPaymentStatus::Failed, $transaction->gross_amount);

    app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, PaymentTrigger::Webhook);

    expect($transaction->fresh()?->status)->toBe(TransactionStatus::Expired);
});

it('processes at most one batch per run', function (): void {
    config(['voting.sweeper.batch_size' => 2]);
    staleOrder(minutesAgo: 30);
    staleOrder(minutesAgo: 30);
    staleOrder(minutesAgo: 30);

    expect(sweep()->checked)->toBe(2);
});

it('runs from the scheduled artisan command', function (): void {
    staleOrder(minutesAgo: 15);

    $this->artisan('payments:reconcile-pending')->assertSuccessful();

    expect($this->gateway->verified)->toHaveCount(1);
});
