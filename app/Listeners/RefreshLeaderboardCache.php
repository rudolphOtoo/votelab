<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FreeVoteCast;
use App\Events\VotesCredited;
use App\Support\LeaderboardCache;

/**
 * Keeps polled standings current as soon as votes land, paid or free.
 *
 * It only invalidates: rebuilding here would run one query per vote, while the next poll's
 * read rebuilds once under LeaderboardCache's lock however many votes landed in between.
 */
final class RefreshLeaderboardCache
{
    public function handleVotesCredited(VotesCredited $event): void
    {
        LeaderboardCache::forget($event->transaction->award_category_id);
    }

    public function handleFreeVoteCast(FreeVoteCast $event): void
    {
        LeaderboardCache::forget($event->voteRecord->award_category_id);
    }
}
