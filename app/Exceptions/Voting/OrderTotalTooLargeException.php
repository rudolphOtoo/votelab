<?php

declare(strict_types=1);

namespace App\Exceptions\Voting;

use InvalidArgumentException;

/**
 * An order's total would exceed the largest amount a transaction amount column holds
 * (PaystackFeeCalculator::MAX_AMOUNT, D-139). QuotePaidVotesAction turns it into a refusal the voter sees.
 */
final class OrderTotalTooLargeException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('Order total is too large.');
    }
}
