<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Nominee;
use App\Models\User;
use App\Support\Audit;
use App\Support\LeaderboardCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Withdraws a nominee: hidden from public pages and refused for new votes (QuotePaidVotesAction,
 * CastFreeVoteAction), while every vote already cast stays in the ledger and the results. A payment
 * started before the archive is still credited when confirmed (DecidePaymentSettlement, D-85). Nominees
 * with votes are archived, never deleted. Audited.
 */
final readonly class ArchiveNomineeAction
{
    /**
     * @throws AuthorizationException
     */
    public function execute(Nominee $nominee, User $actor): Nominee
    {
        $nominee = Nominee::query()->findOrFail($nominee->id);
        Gate::forUser($actor)->authorize('archive', $nominee);

        $archived = Nominee::query()
            ->whereKey($nominee->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now(), 'archived_by_user_id' => $actor->id]) === 1;

        $nominee->refresh();

        if ($archived) {
            LeaderboardCache::forget($nominee->award_category_id);

            Audit::record('nominee.archived', $actor, $nominee, [
                'before' => ['archived' => false],
                'after' => ['archived' => true],
            ]);
        }

        return $nominee;
    }
}
