<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\VoteTransaction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;

/**
 * Catches payments whose webhook never arrived. Re-verifies stale pending transactions through the
 * same crediting action as the webhook and callback, and expires those still unpaid after the
 * expiry window. Expiry only follows a verification that says "not paid", never an outage.
 */
final readonly class ReconcilePendingPaymentsAction
{
    public function __construct(
        private FulfillPaidVoteOrderAction $fulfil,
    ) {}

    public function execute(): ReconciliationSummary
    {
        $verifyBefore = now()->subMinutes((int) config('voting.sweeper.verify_after_minutes'));
        $expireBefore = now()->subHours((int) config('voting.sweeper.expire_after_hours'));

        $references = VoteTransaction::query()
            ->where('status', TransactionStatus::Pending)
            ->where('created_at', '<=', $verifyBefore)
            ->orderBy('id')
            ->limit((int) config('voting.sweeper.batch_size'))
            ->pluck('payment_reference');

        $counts = ['checked' => 0, 'credited' => 0, 'settled' => 0, 'expired' => 0, 'pending' => 0, 'errors' => 0];

        foreach ($references as $reference) {
            $counts['checked']++;

            try {
                $result = $this->fulfil->execute($reference, PaymentTrigger::Sweeper);
            } catch (PaymentGatewayException|LockTimeoutException $e) {
                $counts['errors']++;
                Log::channel('payments')->warning('sweeper.verify_failed', [
                    'reference' => $reference,
                    'trigger' => PaymentTrigger::Sweeper->value,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $transaction = $result->transaction;

            if ($result->newlyCredited) {
                $counts['credited']++;
            } elseif ($transaction?->status !== TransactionStatus::Pending) {
                $counts['settled']++;
            } elseif ($transaction->created_at !== null && $transaction->created_at->lessThanOrEqualTo($expireBefore)) {
                $counts[$this->expire($transaction) ? 'expired' : 'settled']++;
            } else {
                $counts['pending']++;
            }
        }

        $summary = new ReconciliationSummary(
            checked: $counts['checked'],
            credited: $counts['credited'],
            settledWithoutCredit: $counts['settled'],
            expired: $counts['expired'],
            stillPending: $counts['pending'],
            errors: $counts['errors'],
        );

        Log::channel('payments')->info('sweeper.completed', (array) $summary);

        return $summary;
    }

    private function expire(VoteTransaction $transaction): bool
    {
        $affected = VoteTransaction::query()
            ->whereKey($transaction->id)
            ->where('status', TransactionStatus::Pending)
            ->update([
                'status' => TransactionStatus::Expired,
                'status_reason' => 'unpaid_timeout',
            ]);

        Log::channel('payments')->info('sweeper.expired', [
            'reference' => $transaction->payment_reference,
            'vote_transaction_id' => $transaction->id,
            'award_category_id' => $transaction->award_category_id,
            'applied' => $affected === 1,
        ]);

        return $affected === 1;
    }
}
