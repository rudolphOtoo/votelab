<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FreeVoteCast;
use App\Events\VotesCredited;
use App\Support\LeaderboardCache;

/**
 * Keeps polled standings current as soon as votes land, paid or free.
 */
final class RefreshLeaderboardCache
{
    public function handleVotesCredited(VotesCredited $event): void
    {
        LeaderboardCache::refresh($event->transaction->award_category_id);
    }

    public function handleFreeVoteCast(FreeVoteCast $event): void
    {
        LeaderboardCache::refresh($event->voteRecord->award_category_id);
    }
}
