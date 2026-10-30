<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Events\VotesCredited;
use App\Exceptions\PaymentGatewayException;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Support\VoteKeys;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a payment with Paystack and credits its votes exactly once.
 *
 * Shared by the callback and the webhook. Exactly-once is enforced three ways:
 *  1. one cache lock per reference (VoteKeys::fulfilmentLock), waited on rather than failed;
 *  2. a conditional `pending|expired → successful` UPDATE: votes are credited only if it affects one row;
 *  3. a unique vote_records.vote_transaction_id, so the ledger itself rejects a second credit.
 *
 * Only the quantity snapshotted at checkout is credited, never a recalculated one, and only after
 * the provider confirms success for exactly the stored amount, currency and reference.
 */
final readonly class FulfillPaidVoteOrderAction
{
    public function __construct(
        private PaymentGateway $gateway,
        private DecidePaymentSettlement $decide,
    ) {}

    /**
     * Pending and expired transactions are verified; every other status is already settled and returned as is.
     *
     * @throws PaymentGatewayException When Paystack cannot be reached; the transaction stays as it was.
     * @throws LockTimeoutException When another trigger holds the lock for too long; retry later.
     */
    public function execute(string $reference, PaymentTrigger $trigger): FulfilmentResult
    {
        return Cache::lock(VoteKeys::fulfilmentLock($reference), (int) config('voting.fulfilment_lock.seconds'))->block(
            (int) config('voting.fulfilment_lock.wait_seconds'),
            fn(): FulfilmentResult => $this->fulfil($reference, $trigger),
        );
    }

    private function fulfil(string $reference, PaymentTrigger $trigger): FulfilmentResult
    {
        $transaction = VoteTransaction::query()->where('payment_reference', $reference)->first();
        $context = ['reference' => $reference, 'trigger' => $trigger->value];

        if ($transaction === null) {
            Log::channel('payments')->warning('fulfilment.unknown_reference', $context);

            return new FulfilmentResult(null);
        }

        $context += [
            'vote_transaction_id' => $transaction->id,
            'award_program_id' => $transaction->award_program_id,
            'award_category_id' => $transaction->award_category_id,
        ];

        if (!$transaction->status->awaitsPayment()) {
            Log::channel('payments')->info(
                'fulfilment.already_settled',
                $context
                + [
                    'status' => $transaction->status->value,
                ],
            );

            return new FulfilmentResult($transaction);
        }

        $verification = $this->gateway->verify($reference);
        $context += [
            'provider_status' => $verification->providerStatus,
            'expected_amount' => $transaction->gross_amount,
            'verified_amount' => $verification->amount,
            'verified_currency' => $verification->currency,
        ];

        $decision = $this->decide->execute($transaction, $verification, $transaction->awardCategory()->firstOrFail());

        if ($decision->settleAs !== null) {
            return $this->settle($transaction, $decision->settleAs, $decision->reason, $context);
        }

        if (!$decision->credit) {
            Log::channel('payments')->info("fulfilment.{$decision->reason}", $context);

            return new FulfilmentResult($transaction);
        }

        return $this->credit($transaction, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function credit(VoteTransaction $transaction, array $context): FulfilmentResult
    {
        $credited = DB::transaction(static function () use ($transaction): bool {
            $now = now();

            $affected = VoteTransaction::query()
                ->whereKey($transaction->id)
                ->whereIn('status', TransactionStatus::awaitingPayment())
                ->update([
                    'status' => TransactionStatus::Successful,
                    'status_reason' => null,
                    'verified_at' => $now,
                    'credited_at' => $now,
                ]);

            if ($affected !== 1) {
                return false;
            }

            Nominee::query()->whereKey($transaction->nominee_id)->increment('votes_count', $transaction->quantity);

            VoteRecord::query()->create([
                'organization_id' => $transaction->organization_id,
                'award_program_id' => $transaction->award_program_id,
                'award_category_id' => $transaction->award_category_id,
                'nominee_id' => $transaction->nominee_id,
                'vote_transaction_id' => $transaction->id,
                'is_paid' => true,
                'quantity' => $transaction->quantity,
                'cast_at' => $now,
            ]);

            return true;
        });

        $transaction->refresh();

        if (!$credited) {
            Log::channel('payments')->info(
                'fulfilment.lost_race',
                $context + ['status' => $transaction->status->value],
            );

            return new FulfilmentResult($transaction);
        }

        VotesCredited::dispatch($transaction);

        Log::channel('payments')->info(
            'fulfilment.credited',
            $context
            + [
                'nominee_id' => $transaction->nominee_id,
                'quantity' => $transaction->quantity,
                'gross_amount' => $transaction->gross_amount,
            ],
        );

        return new FulfilmentResult($transaction, newlyCredited: true);
    }

    /**
     * Settle a pending transaction without crediting votes.
     *
     * @param  array<string, mixed>  $context
     */
    private function settle(
        VoteTransaction $transaction,
        TransactionStatus $status,
        string $reason,
        array $context,
    ): FulfilmentResult {
        $affected = VoteTransaction::query()
            ->whereKey($transaction->id)
            ->whereIn('status', TransactionStatus::awaitingPayment())
            ->update([
                'status' => $status,
                'status_reason' => $reason,
                'verified_at' => now(),
            ]);

        $transaction->refresh();

        $level = $status === TransactionStatus::Failed ? 'info' : 'warning';
        Log::channel('payments')->log(
            $level,
            "fulfilment.{$status->value}",
            $context
            + [
                'reason' => $reason,
                'applied' => $affected === 1,
            ],
        );

        return new FulfilmentResult($transaction);
    }
}
