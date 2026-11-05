<?php

declare(strict_types=1);

namespace App\Exceptions\Voting;

use RuntimeException;

/**
 * A free vote cannot be cast. The message is safe to show to the voter.
 */
final class FreeVoteUnavailableException extends RuntimeException
{
    public static function votingNotOpen(): self
    {
        return new self('Voting is not open for this category.');
    }

    public static function freeVotesNotAccepted(): self
    {
        return new self('This category does not accept free votes.');
    }

    public static function limitReached(int $limit): self
    {
        return new self(
            $limit === 1
                ? "You've already used today's free vote in this category. Come back tomorrow, or buy votes to keep supporting."
                : "You've used all {$limit} of today's free votes in this category. Come back tomorrow, or buy votes to keep supporting.",
        );
    }
}
