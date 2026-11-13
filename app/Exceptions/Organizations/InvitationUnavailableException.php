<?php

declare(strict_types=1);

namespace App\Exceptions\Organizations;

use RuntimeException;

/**
 * An invitation that can't be accepted right now. The message is safe to show to whoever holds the link.
 */
final class InvitationUnavailableException extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('invalid', 'This invitation link is not valid. Ask the organizer to send a new one.');
    }

    public static function expired(): self
    {
        return new self('expired', 'This invitation has expired. Ask the organizer to send a new one.');
    }

    public static function used(): self
    {
        return new self('used', 'This invitation has already been used. Sign in to continue.');
    }

    public static function revoked(): self
    {
        return new self('revoked', 'This invitation was withdrawn. Ask the organizer if you still need access.');
    }

    public static function alreadyMember(): self
    {
        return new self('already_member', 'You are already a member of this organization.');
    }

    public static function wrongAccount(string $invitedEmail, string $signedInEmail): self
    {
        return new self(
            'wrong_account',
            "This invitation is for {$invitedEmail}, but you are signed in as {$signedInEmail}. "
            . 'Sign out, then open the link again and sign in with the invited email.',
        );
    }

    public static function signInRequired(string $invitedEmail): self
    {
        return new self(
            'sign_in_required',
            "{$invitedEmail} already has a VoteLab account. Sign in with it to accept this invitation.",
        );
    }
}
