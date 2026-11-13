<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AwardCategory;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Stale-while-revalidate cache of a category's standings, so polling never queries per request.
 * Reads the derived nominees.votes_count counter (one indexed read, no aggregates).
 *
 * Two keys per category: the standings (the last good copy, kept for STALE_SECONDS) and a
 * freshness marker (kept for voting.leaderboard_cache_seconds). New votes delete only the marker.
 *  - fresh copy: served as is;
 *  - stale copy: one request takes the rebuild lock and rebuilds; everyone else gets the stale copy;
 *  - no copy: requests wait briefly on the lock, then reuse the rebuilt copy. Only if no copy
 *    appears within REBUILD_WAIT_SECONDS is the counter read directly (and not cached).
 *
 * Fails closed: standings() reads the category's current status from the database (one primary-key
 * lookup of the status column) and returns null, without touching the cache, unless its results are
 * public (CategoryStatus::isLeaderboardPublic()). It never trusts the status on the caller's model, so a
 * model held across polls can't leak counts after the category goes blind. Callers may check visibility
 * as well (CategoryShow does, as a second layer) but never need to for safety.
 */
final class LeaderboardCache
{
    /**
     * Seconds the last good copy is kept, so a stale copy exists to serve while a rebuild runs.
     */
    private const int STALE_SECONDS = 600;

    /**
     * Seconds the rebuild lock lives for; far longer than the single indexed read it guards.
     */
    private const int REBUILD_LOCK_SECONDS = 5;

    /**
     * Seconds a request with no copy at all waits for another request's rebuild.
     */
    private const int REBUILD_WAIT_SECONDS = 2;

    /**
     * @return array<int, int>|null nominee_id => votes, or null while the category's results are hidden
     */
    public static function standings(AwardCategory $category): ?array
    {
        $awardCategoryId = $category->id;

        if (!LeaderboardSource::resultsArePublic($awardCategoryId)) {
            return null;
        }

        $copy = self::copy($awardCategoryId);

        if ($copy === null) {
            return self::buildMissing($awardCategoryId);
        }

        if (Cache::has(VoteKeys::leaderboardFresh($awardCategoryId))) {
            return $copy;
        }

        return self::revalidate($awardCategoryId, $copy);
    }

    /**
     * Mark the cached standings stale, e.g. right after votes are credited. The copy stays, so readers
     * keep getting it while the next read rebuilds.
     */
    public static function forget(int $awardCategoryId): void
    {
        Cache::forget(VoteKeys::leaderboardFresh($awardCategoryId));
    }

    /**
     * Drop the stored copy and the fresh marker, so nothing built under a previous status can be served
     * (D-68: status changes and reveals).
     */
    public static function purge(int $awardCategoryId): void
    {
        Cache::forget(VoteKeys::leaderboard($awardCategoryId));
        Cache::forget(VoteKeys::leaderboardFresh($awardCategoryId));
    }

    /**
     * Build the standings now, so the first visitors after results go public hit a fresh copy instead of
     * the cold path (D-69). Does nothing while results are hidden.
     */
    public static function warm(int $awardCategoryId): void
    {
        if (LeaderboardSource::resultsArePublic($awardCategoryId)) {
            self::rebuild($awardCategoryId);
        }
    }

    /**
     * A stale copy exists: rebuild if no one else is, otherwise serve the stale copy without waiting.
     *
     * @param  array<int, int>  $stale
     * @return array<int, int>
     */
    private static function revalidate(int $awardCategoryId, array $stale): array
    {
        $lock = Cache::lock(VoteKeys::leaderboardRebuildLock($awardCategoryId), self::REBUILD_LOCK_SECONDS);

        if (!$lock->get()) {
            return $stale;
        }

        try {
            // Another request may have finished a rebuild between our read and taking the lock.
            if (Cache::has(VoteKeys::leaderboardFresh($awardCategoryId))) {
                return self::copy($awardCategoryId) ?? self::rebuild($awardCategoryId);
            }

            return self::rebuild($awardCategoryId);
        } finally {
            $lock->release();
        }
    }

    /**
     * No copy at all (cold cache or evicted): wait for one rebuild rather than all rebuilding.
     *
     * @return array<int, int>
     */
    private static function buildMissing(int $awardCategoryId): array
    {
        try {
            /** @var array<int, int> */
            return Cache::lock(VoteKeys::leaderboardRebuildLock($awardCategoryId), self::REBUILD_LOCK_SECONDS)->block(
                self::REBUILD_WAIT_SECONDS,
                static fn(): array => self::copy($awardCategoryId) ?? self::rebuild($awardCategoryId),
            );
        } catch (LockTimeoutException) {
            // No copy to serve and the rebuild is stuck: read the counter directly so the page still works.
            return LeaderboardSource::standings($awardCategoryId);
        }
    }

    /**
     * @return array<int, int>|null
     */
    private static function copy(int $awardCategoryId): ?array
    {
        $copy = Cache::get(VoteKeys::leaderboard($awardCategoryId));

        /** @var array<int, int>|null */
        return is_array($copy) ? $copy : null;
    }

    /**
     * @return array<int, int>
     */
    private static function rebuild(int $awardCategoryId): array
    {
        $standings = LeaderboardSource::standings($awardCategoryId);

        Cache::put(VoteKeys::leaderboard($awardCategoryId), $standings, self::STALE_SECONDS);
        Cache::put(
            VoteKeys::leaderboardFresh($awardCategoryId),
            true,
            (int) config('voting.leaderboard_cache_seconds'),
        );

        return $standings;
    }
}
