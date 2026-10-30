<?php

declare(strict_types=1);

namespace App\Exceptions\Voting;

use RuntimeException;
use Throwable;

/**
 * A paid-vote checkout cannot be started. The message is safe to show to the voter.
 */
final class CheckoutUnavailableException extends RuntimeException
{
    public static function votingNotOpen(): self
    {
        return new self('Voting is not open for this category.');
    }

    public static function paidVotesNotAccepted(): self
    {
        return new self('This category does not accept paid votes.');
    }

    public static function invalidQuantity(int $max): self
    {
        return new self("Choose between 1 and {$max} votes.");
    }

    public static function invalidEmail(): self
    {
        return new self('Enter a valid email address, or leave it blank.');
    }

    public static function gatewayUnavailable(Throwable $previous): self
    {
        return new self('We could not reach the payment provider. Please try again.', previous: $previous);
    }
}
