<?php

declare(strict_types=1);

use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Events\VotesCredited;
use App\Exceptions\PaymentGatewayException;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Support\VoteKeys;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * A pending paid order as InitiatePaidVoteOrderAction would leave it, known to the fake gateway.
 */
function pendingOrder(int $quantity = 10, CategoryStatus $status = CategoryStatus::Open): VoteTransaction
{
    $category = AwardCategory::factory()->status($status)->create();
    $transaction = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create([
        'quantity' => $quantity,
        'unit_price' => 100,
        'net_amount' => $quantity * 100,
        'fee_amount' => 20,
        'gross_amount' => ($quantity * 100) + 20,
    ]);

    test()->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    return $transaction;
}

function fulfil(
    VoteTransaction $transaction,
    PaymentTrigger $trigger = PaymentTrigger::Webhook,
): App\Actions\Voting\FulfilmentResult {
    return app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, $trigger);
}

beforeEach(function (): void {
    $this->gateway = fakeGateway();
});

it('credits a verified payment exactly once: status, counter and ledger', function (): void {
    Event::fake([VotesCredited::class]);
    $transaction = pendingOrder(quantity: 10);

    $result = fulfil($transaction);

    $transaction->refresh();
    expect($result->newlyCredited)
        ->toBeTrue()
        ->and($transaction->status)
        ->toBe(TransactionStatus::Successful)
        ->and($transaction->verified_at)
        ->not->toBeNull()->and($transaction->credited_at)
        ->not->toBeNull()->and($transaction->nominee->votes_count)->toBe(10)->and(
            VoteRecord::query()->sole()->only(['vote_transaction_id', 'is_paid', 'quantity']),
        )->toBe(['vote_transaction_id' => $transaction->id, 'is_paid' => true, 'quantity' => 10]);

    Event::assertDispatchedTimes(VotesCredited::class, 1);
});

it('is idempotent across repeated deliveries and both triggers', function (): void {
    $transaction = pendingOrder(quantity: 7);

    $first = fulfil($transaction, PaymentTrigger::Webhook);
    $second = fulfil($transaction, PaymentTrigger::Webhook);
    $third = fulfil($transaction, PaymentTrigger::Callback);

    expect($first->newlyCredited)
        ->toBeTrue()
        ->and($second->newlyCredited)
        ->toBeFalse()
        ->and($third->newlyCredited)
        ->toBeFalse()
        ->and($third->transaction?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(7)
        ->and(VoteRecord::query()->count())
        ->toBe(1)
        ->and($this->gateway->verified)
        ->toHaveCount(1); // settled transactions are not re-verified
});

it('credits once when callback arrives first and the webhook follows', function (): void {
    $transaction = pendingOrder(quantity: 3);

    fulfil($transaction, PaymentTrigger::Callback);
    fulfil($transaction, PaymentTrigger::Webhook);

    expect($transaction->nominee->fresh()?->votes_count)->toBe(3);
});

it('credits nothing when a racing worker settles the transaction mid-verification', function (): void {
    $transaction = pendingOrder(quantity: 5);

    // Another worker (e.g. one that held an expired lock) credits while this one is verifying.
    $this->gateway->beforeVerifyReturns(static function () use ($transaction): void {
        VoteTransaction::query()->whereKey($transaction->id)->update(['status' => TransactionStatus::Successful]);
    });

    $result = fulfil($transaction);

    expect($result->newlyCredited)
        ->toBeFalse()
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(0)
        ->and(VoteRecord::query()->count())
        ->toBe(0);
});

it('rolls the whole credit back if the ledger refuses a duplicate', function (): void {
    $transaction = pendingOrder(quantity: 5);
    VoteRecord::factory()->for($transaction->nominee)->create([
        'vote_transaction_id' => $transaction->id,
        'is_paid' => true,
    ]);

    expect(fn() => fulfil($transaction))->toThrow(Illuminate\Database\QueryException::class);

    expect($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Pending)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(0);
});

it('waits for, then times out on, a lock held by another trigger without crediting', function (): void {
    config(['voting.fulfilment_lock.wait_seconds' => 1]);
    $transaction = pendingOrder();
    $held = Cache::lock(VoteKeys::fulfilmentLock($transaction->payment_reference), 30);
    $held->get();

    expect(fn() => fulfil($transaction))->toThrow(LockTimeoutException::class);
    expect($transaction->fresh()?->status)->toBe(TransactionStatus::Pending);

    $held->release();
    expect(fulfil($transaction)->newlyCredited)->toBeTrue();
});

it('flags and credits nothing when the paid amount differs from the snapshot', function (int $delta): void {
    $transaction = pendingOrder();
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount + $delta);

    fulfil($transaction);

    $transaction->refresh();
    expect($transaction->status)
        ->toBe(TransactionStatus::Flagged)
        ->and($transaction->status_reason)
        ->toBe('amount_mismatch')
        ->and($transaction->nominee->votes_count)
        ->toBe(0)
        ->and(VoteRecord::query()->count())
        ->toBe(0);
})->with(['underpaid' => -1, 'overpaid' => 1]);

it('flags a payment made in the wrong currency', function (): void {
    $transaction = pendingOrder();
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount, currency: 'NGN');

    fulfil($transaction);

    expect($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Flagged)
        ->and($transaction->fresh()?->status_reason)
        ->toBe('currency_mismatch');
});

it('flags a verification that answers for a different reference', function (): void {
    $transaction = pendingOrder();
    $this->gateway->reports(
        $transaction->payment_reference,
        amount: $transaction->gross_amount,
        reportedReference: 'VL_SOMETHINGELSE',
    );

    fulfil($transaction);

    expect($transaction->fresh()?->status_reason)->toBe('reference_mismatch');
});

it('marks a failed payment failed and credits nothing', function (): void {
    $transaction = pendingOrder();
    $this->gateway->reports($transaction->payment_reference, GatewayPaymentStatus::Failed, $transaction->gross_amount);

    fulfil($transaction);

    expect($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Failed)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(0);
});

it('leaves a still-processing payment pending so a later delivery can credit it', function (): void {
    $transaction = pendingOrder(quantity: 2);
    $this->gateway->reports($transaction->payment_reference, GatewayPaymentStatus::Pending, $transaction->gross_amount);

    expect(fulfil($transaction)->transaction?->status)->toBe(TransactionStatus::Pending);

    $this->gateway->reports($transaction->payment_reference, GatewayPaymentStatus::Success, $transaction->gross_amount);

    expect(fulfil($transaction)->newlyCredited)->toBeTrue()->and($transaction->nominee->fresh()?->votes_count)->toBe(2);
});

it('leaves the transaction pending when Paystack cannot be reached', function (): void {
    $transaction = pendingOrder();
    $this->gateway->failVerification();

    expect(fn() => fulfil($transaction))->toThrow(PaymentGatewayException::class);
    expect($transaction->fresh()?->status)->toBe(TransactionStatus::Pending);
});

it('ignores references it never issued', function (): void {
    $result = app(FulfillPaidVoteOrderAction::class)->execute('VL_UNKNOWN', PaymentTrigger::Webhook);

    expect($result->transaction)->toBeNull()->and($this->gateway->verified)->toBeEmpty();
});

it('credits embargoed categories', function (): void {
    $transaction = pendingOrder(quantity: 4, status: CategoryStatus::Embargoed);

    expect(fulfil($transaction)->newlyCredited)->toBeTrue();
});

it('credits a payment started before close and verified inside the grace window', function (): void {
    config(['voting.close_grace_minutes' => 5]);
    Carbon::setTestNow('2026-10-30 20:00:00');
    $transaction = pendingOrder(quantity: 6);

    Carbon::setTestNow('2026-10-30 20:01:00');
    $transaction->awardCategory->forceFill(['status' => CategoryStatus::Closed, 'closed_at' => now()])->save();

    Carbon::setTestNow('2026-10-30 20:05:59');
    expect(fulfil($transaction)->newlyCredited)->toBeTrue();
});

it('records paid_uncredited when the payment is verified after the grace window', function (): void {
    config(['voting.close_grace_minutes' => 5]);
    Carbon::setTestNow('2026-10-30 20:00:00');
    $transaction = pendingOrder(quantity: 6);

    Carbon::setTestNow('2026-10-30 20:01:00');
    $transaction->awardCategory->forceFill(['status' => CategoryStatus::Closed, 'closed_at' => now()])->save();

    Carbon::setTestNow('2026-10-30 20:06:00');
    fulfil($transaction);

    $transaction->refresh();
    expect($transaction->status)
        ->toBe(TransactionStatus::PaidUncredited)
        ->and($transaction->status_reason)
        ->toBe('category_closed')
        ->and($transaction->verified_at)
        ->not
        ->toBeNull()
        ->and($transaction->nominee->votes_count)
        ->toBe(0);
});

it('records paid_uncredited for a checkout started after the category closed', function (): void {
    Carbon::setTestNow('2026-10-30 20:00:00');
    $category = AwardCategory::factory()->status(CategoryStatus::Closed)->create(['closed_at' => now()->subMinute()]);
    $transaction = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create();
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    fulfil($transaction);

    expect($transaction->fresh()?->status)->toBe(TransactionStatus::PaidUncredited);
});

it('records paid_uncredited once results are revealed', function (): void {
    $transaction = pendingOrder(status: CategoryStatus::Revealed);

    fulfil($transaction);

    expect($transaction->fresh()?->status)
        ->toBe(TransactionStatus::PaidUncredited)
        ->and($transaction->fresh()?->status_reason)
        ->toBe('category_revealed');
});

it('increments counters atomically in SQL rather than read-modify-write', function (): void {
    $transaction = pendingOrder(quantity: 9);
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    fulfil($transaction);

    expect(collect($queries)->contains(
        static fn(string $sql): bool => str_contains($sql, '"votes_count" = "votes_count" + 9'),
    ))
        ->toBeTrue()
        ->and(collect($queries)->contains(
            static fn(string $sql): bool => (
                str_contains($sql, '"status" = ?') && str_starts_with($sql, 'update "vote_transactions"')
            ),
        ))
        ->toBeTrue();
});
