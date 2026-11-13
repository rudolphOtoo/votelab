<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\TransactionStatus;

/**
 * What to do with a transaction after Paystack has answered for it.
 */
final readonly class SettlementDecision
{
    private function __construct(
        public bool $credit, // Credit the stored quantity (status becomes successful)
        public ?TransactionStatus $settleAs, // Settle without credit; null with $credit false means keep waiting
        public string $reason,
    ) {}

    public static function credit(): self
    {
        return new self(credit: true, settleAs: null, reason: 'verified');
    }

    public static function wait(string $reason): self
    {
        return new self(credit: false, settleAs: null, reason: $reason);
    }

    public static function settle(TransactionStatus $status, string $reason): self
    {
        return new self(credit: false, settleAs: $status, reason: $reason);
    }
}
