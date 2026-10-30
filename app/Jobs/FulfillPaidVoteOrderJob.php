<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies and credits a payment announced by a Paystack webhook, off the request path.
 * Retries while Paystack is unreachable, another trigger holds the lock, or the payment is still processing.
 */
final class FulfillPaidVoteOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /** Seconds before a stuck attempt is killed; covers the lock wait plus one Paystack round trip. */
    public int $timeout = 60;

    public function __construct(
        public readonly string $reference,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 1800];
    }

    public function handle(FulfillPaidVoteOrderAction $fulfil): void
    {
        try {
            $result = $fulfil->execute($this->reference, PaymentTrigger::Webhook);
        } catch (PaymentGatewayException|LockTimeoutException $e) {
            Log::channel('payments')->warning('fulfilment.retry', [
                'reference' => $this->reference,
                'trigger' => PaymentTrigger::Webhook->value,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            $this->release($this->backoff()[min($this->attempts() - 1, 6)]);

            return;
        }

        if ($result->transaction?->status === TransactionStatus::Pending) {
            $this->release($this->backoff()[min($this->attempts() - 1, 6)]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::channel('payments')->error('fulfilment.gave_up', [
            'reference' => $this->reference,
            'trigger' => PaymentTrigger::Webhook->value,
            'error' => $e->getMessage(),
        ]);
    }
}
