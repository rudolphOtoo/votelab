<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache of a category's standings, so polling never queries per request.
 * Reads the derived nominees.votes_count counter (one indexed read, no aggregates).
 *
 * New votes only invalidate the entry; the next read rebuilds it. A miss is rebuilt under a
 * per-category lock and re-checked once the lock is held, so a burst of concurrent misses
 * (every poller after a vote lands) runs one rebuild query, not one per request.
 */
final class LeaderboardCache
{
    /**
     * Seconds the rebuild lock lives for; far longer than the single indexed read it guards.
     */
    private const int REBUILD_LOCK_SECONDS = 5;

    /**
     * Seconds a request waits for another request's rebuild before reading the database itself.
     */
    private const int REBUILD_WAIT_SECONDS = 2;

    /**
     * @return array<int, int> nominee_id => votes
     */
    public static function standings(int $awardCategoryId): array
    {
        $cached = self::cached($awardCategoryId);

        if ($cached !== null) {
            return $cached;
        }

        try {
            /** @var array<int, int> */
            return Cache::lock(VoteKeys::leaderboardRebuildLock($awardCategoryId), self::REBUILD_LOCK_SECONDS)->block(
                self::REBUILD_WAIT_SECONDS,
                static fn(): array => self::cached($awardCategoryId) ?? self::rebuild($awardCategoryId),
            );
        } catch (LockTimeoutException) {
            // A stuck rebuild must not break the page: read the counter directly, without caching it.
            return self::load($awardCategoryId);
        }
    }

    /**
     * Drop the cached standings, e.g. right after votes are credited; the next read rebuilds them.
     */
    public static function forget(int $awardCategoryId): void
    {
        Cache::forget(VoteKeys::leaderboard($awardCategoryId));
    }

    /**
     * Replace the cached standings with fresh ones now.
     */
    public static function refresh(int $awardCategoryId): void
    {
        self::rebuild($awardCategoryId);
    }

    /**
     * @return array<int, int>|null
     */
    private static function cached(int $awardCategoryId): ?array
    {
        $cached = Cache::get(VoteKeys::leaderboard($awardCategoryId));

        /** @var array<int, int>|null */
        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array<int, int>
     */
    private static function rebuild(int $awardCategoryId): array
    {
        $standings = self::load($awardCategoryId);

        Cache::put(
            VoteKeys::leaderboard($awardCategoryId),
            $standings,
            (int) config('voting.leaderboard_cache_seconds'),
        );

        return $standings;
    }

    /**
     * @return array<int, int>
     */
    private static function load(int $awardCategoryId): array
    {
        return Nominee::query()
            ->where('award_category_id', $awardCategoryId)
            ->pluck('votes_count', 'id')
            ->map(static fn(mixed $votes): int => (int) $votes)
            ->all();
    }
}
