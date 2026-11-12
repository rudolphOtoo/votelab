<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Nominee;
use App\Models\User;
use App\Models\VoteRecord;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;

/**
 * Compares each nominee's derived votes_count in a category with vote_records, the ledger (D-05), and
 * rewrites the counter from the ledger unless $dryRun. Every difference is an alert: logged at error level
 * on the `voting` channel (Sentry picks these up in Stage 3) and written to the audit trail per nominee,
 * with the counter before and the ledger value after. Used by RevealWinnerAction inside its transaction and
 * by votes:rebuild-tallies (Stage 3). Archived nominees are included: their votes still count.
 */
final class RecountCategoryTallies
{
    /**
     * @return array<int, array{counter: int, ledger: int}>  nominee_id => drift found (empty when none)
     */
    public static function apply(int $awardCategoryId, ?User $actor = null, bool $dryRun = false): array
    {
        $ledger = VoteRecord::query()
            ->where('award_category_id', $awardCategoryId)
            ->groupBy('nominee_id')
            ->selectRaw('nominee_id, SUM(quantity) as votes')
            ->pluck('votes', 'nominee_id');
        $drift = [];

        $nominees = Nominee::query()->where('award_category_id', $awardCategoryId)->get([
            'id',
            'organization_id',
            'award_category_id',
            'votes_count',
        ]);

        foreach ($nominees as $nominee) {
            $votes = (int) ($ledger[$nominee->id] ?? 0);

            if ($votes === $nominee->votes_count) {
                continue;
            }

            $drift[$nominee->id] = ['counter' => $nominee->votes_count, 'ledger' => $votes];
            self::alert($nominee, $votes, $actor, $dryRun);

            if (!$dryRun) {
                Nominee::query()->whereKey($nominee->id)->update(['votes_count' => $votes]);
            }
        }

        return $drift;
    }

    private static function alert(Nominee $nominee, int $ledgerVotes, ?User $actor, bool $dryRun): void
    {
        Log::channel('voting')->error('tally.drift', [
            'award_category_id' => $nominee->award_category_id,
            'nominee_id' => $nominee->id,
            'counter' => $nominee->votes_count,
            'ledger' => $ledgerVotes,
            'fixed' => !$dryRun,
        ]);

        Audit::record(
            $dryRun ? 'tally.drift_found' : 'tally.drift_corrected',
            $actor,
            $nominee,
            ['before' => ['votes_count' => $nominee->votes_count], 'after' => ['votes_count' => $ledgerVotes]],
            ['award_category_id' => $nominee->award_category_id, 'dry_run' => $dryRun],
        );
    }
}
