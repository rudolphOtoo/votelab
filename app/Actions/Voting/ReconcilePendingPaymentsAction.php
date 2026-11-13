<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\VoteTransaction;
use App\Support\Observability\Alert;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Catches payments whose webhook never arrived. Re-verifies stale pending transactions through the
 * same crediting action as the webhook and callback, and expires those still unpaid after the
 * expiry window. Expiry only follows a verification that says "not paid", never an outage.
 *
 * A reference that fails is logged and skipped, never allowed to stop the run: references are swept in id
 * order, so one that always failed would otherwise block every later one on every run. Unexpected failures go
 * to Sentry; a reference that fails STUCK_AFTER_RUNS runs in a row raises an alert (sweeper.reference_stuck).
 */
final readonly class ReconcilePendingPaymentsAction
{
    public const int STUCK_AFTER_RUNS = 3;

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
                $outcome = $this->sweep($reference, $expireBefore);
            } catch (PaymentGatewayException|LockTimeoutException $e) {
                // Expected: a Paystack outage (the gateway reports it) or another worker holding the order.
                $outcome = 'errors';
                $this->failed($reference, $e);
            } catch (Throwable $e) {
                $outcome = 'errors';
                report($e);
                $this->failed($reference, $e);
            }

            $counts[$outcome]++;
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

    /**
     * @return 'credited'|'settled'|'expired'|'pending'
     */
    private function sweep(string $reference, CarbonInterface $expireBefore): string
    {
        $result = $this->fulfil->execute($reference, PaymentTrigger::Sweeper);
        Cache::forget(self::failuresKey($reference));
        $transaction = $result->transaction;

        if ($result->newlyCredited) {
            return 'credited';
        }

        if ($transaction?->status !== TransactionStatus::Pending) {
            return 'settled';
        }

        if ($transaction->created_at !== null && $transaction->created_at->lessThanOrEqualTo($expireBefore)) {
            return $this->expire($transaction) ? 'expired' : 'settled';
        }

        return 'pending';
    }

    private function failed(string $reference, Throwable $e): void
    {
        $key = self::failuresKey($reference);
        Cache::add($key, 0, now()->addDay());
        $consecutive = (int) Cache::increment($key);

        Log::channel('payments')->warning('sweeper.verify_failed', [
            'reference' => $reference,
            'trigger' => PaymentTrigger::Sweeper->value,
            'error' => $e->getMessage(),
            'exception' => $e::class,
            'consecutive_failures' => $consecutive,
        ]);

        if ($consecutive === self::STUCK_AFTER_RUNS) {
            Alert::raise('sweeper.reference_stuck', [
                'reference' => $reference,
                'consecutive_failures' => $consecutive,
                'exception' => $e::class,
            ]);
        }
    }

    private static function failuresKey(string $reference): string
    {
        return "sweeper:failures:{$reference}";
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
