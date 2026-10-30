<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The single source of cache and lock keys used by the voting domain.
 */
final class VoteKeys
{
    /**
     * Lock held while one payment reference is verified and credited. Shared by the
     * callback and webhook paths so they serialise on the same key.
     */
    public static function fulfilmentLock(string $reference): string
    {
        return "voting:fulfil:{$reference}";
    }
}
