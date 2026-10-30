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

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Successful => 'Successful',
            self::Failed => 'Failed',
            self::Flagged => 'Flagged',
            self::PaidUncredited => 'Paid, not credited',
            self::Refunded => 'Refunded',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Flagged, self::PaidUncredited], true);
    }
}
