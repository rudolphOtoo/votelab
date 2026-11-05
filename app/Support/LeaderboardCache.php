<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache of a category's standings, so polling never queries per request.
 * Reads the derived nominees.votes_count counter (one indexed read, no aggregates).
 */
final class LeaderboardCache
{
    /**
     * @return array<int, int> nominee_id => votes
     */
    public static function standings(int $awardCategoryId): array
    {
        /** @var array<int, int> */
        return Cache::remember(
            VoteKeys::leaderboard($awardCategoryId),
            (int) config('voting.leaderboard_cache_seconds'),
            static fn(): array => self::load($awardCategoryId),
        );
    }

    /**
     * Replace the cached standings with fresh ones, e.g. right after votes are credited.
     */
    public static function refresh(int $awardCategoryId): void
    {
        Cache::put(
            VoteKeys::leaderboard($awardCategoryId),
            self::load($awardCategoryId),
            (int) config('voting.leaderboard_cache_seconds'),
        );
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
