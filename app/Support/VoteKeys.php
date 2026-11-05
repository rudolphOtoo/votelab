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

    /**
     * Cached [nominee_id => votes] standings for one category, read by public and stage polling.
     */
    public static function leaderboard(int $awardCategoryId): string
    {
        return "voting:leaderboard:{$awardCategoryId}";
    }

    /**
     * Held while one voter starts a checkout for one nominee, so a double submit cannot start two.
     */
    public static function checkoutLock(string $voterKey, int $nomineeId): string
    {
        return "voting:checkout-lock:{$voterKey}:{$nomineeId}";
    }

    /**
     * The checkout a voter just started for one order, reused if the same order is submitted again.
     */
    public static function recentCheckout(string $voterKey, int $nomineeId, int $quantity, string $email): string
    {
        return 'voting:checkout:' . hash('sha256', "{$voterKey}|{$nomineeId}|{$quantity}|{$email}");
    }
}
