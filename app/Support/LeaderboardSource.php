<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\Nominee;

/**
 * The database reads behind LeaderboardCache: the visibility gate and the standings themselves.
 */
final class LeaderboardSource
{
    /**
     * The gate (D-67): the category's status as stored now, not as a caller last loaded it. One
     * primary-key read of the status column. A missing category counts as hidden.
     */
    public static function resultsArePublic(int $awardCategoryId): bool
    {
        $status = AwardCategory::query()->whereKey($awardCategoryId)->value('status');

        if (is_string($status)) {
            $status = CategoryStatus::tryFrom($status);
        }

        return $status instanceof CategoryStatus && $status->isLeaderboardPublic();
    }

    /**
     * nominee_id => votes, from the derived nominees.votes_count counter (one indexed read, no aggregates).
     *
     * @return array<int, int>
     */
    public static function standings(int $awardCategoryId): array
    {
        return Nominee::query()
            ->where('award_category_id', $awardCategoryId)
            ->pluck('votes_count', 'id')
            ->map(static fn(mixed $votes): int => (int) $votes)
            ->all();
    }
}
