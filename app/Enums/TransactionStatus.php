<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Flagged = 'flagged'; // Verification mismatch (amount, currency, reference); nothing credited
    case PaidUncredited = 'paid_uncredited'; // Paid after the category stopped accepting votes; needs a refund
    case Refunded = 'refunded';
    case Expired = 'expired'; // Unpaid long after checkout started; a late success is still settled

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Successful => 'Successful',
            self::Failed => 'Failed',
            self::Flagged => 'Flagged',
            self::PaidUncredited => 'Paid, not credited',
            self::Refunded => 'Refunded',
            self::Expired => 'Expired',
        };
    }

    /**
     * Whether a successful verification may still settle this transaction. Expired transactions
     * stay open to a late success: the voter may have paid after the sweeper gave up.
     */
    public function awaitsPayment(): bool
    {
        return in_array($this, [self::Pending, self::Expired], true);
    }

    /**
     * @return list<self>
     */
    public static function awaitingPayment(): array
    {
        return [self::Pending, self::Expired];
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Flagged, self::PaidUncredited], true);
    }
}
