<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AwardCategory;
use App\Models\Nominee;
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
 * Fails closed: standings() returns null, without touching the cache, unless the category's results
 * are public (CategoryStatus::isLeaderboardPublic()). Callers may check visibility as well (CategoryShow
 * does, as a second layer) but never need to for safety. The gate trusts the status of the model it is
 * given, so pass one loaded in the current request, not one held across polls.
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
        if (!$category->status->isLeaderboardPublic()) {
            return null;
        }

        $awardCategoryId = $category->id;
        $copy = self::copy($awardCategoryId);

        if ($copy === null) {
            return self::buildMissing($awardCategoryId);
        }

        if (self::isFresh($awardCategoryId)) {
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
     * Replace the cached standings with fresh ones now.
     *
     * @deprecated Nothing in app code calls this since D-64; only tests use it to seed the cache.
     *             Remove in Stage 3 (hardening) once those tests seed through standings().
     */
    public static function refresh(int $awardCategoryId): void
    {
        self::rebuild($awardCategoryId);
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
            if (self::isFresh($awardCategoryId)) {
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
            return self::load($awardCategoryId);
        }
    }

    private static function isFresh(int $awardCategoryId): bool
    {
        return Cache::has(VoteKeys::leaderboardFresh($awardCategoryId));
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
        $standings = self::load($awardCategoryId);

        Cache::put(VoteKeys::leaderboard($awardCategoryId), $standings, self::STALE_SECONDS);
        Cache::put(
            VoteKeys::leaderboardFresh($awardCategoryId),
            true,
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
