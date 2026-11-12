<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Nominee;
use App\Models\VoteRecord;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites each nominee's derived votes_count in a category from vote_records, the ledger (D-05), and logs
 * any drift. Used by RevealWinnerAction inside its transaction; the votes:rebuild-tallies command (Stage 3)
 * reuses it. Archived nominees are included: their votes still count.
 */
final class RecountCategoryTallies
{
    /**
     * @return array<int, array{counter: int, ledger: int}>  nominee_id => drift found (empty when none)
     */
    public static function apply(int $awardCategoryId, bool $dryRun = false): array
    {
        $ledger = VoteRecord::query()
            ->where('award_category_id', $awardCategoryId)
            ->groupBy('nominee_id')
            ->selectRaw('nominee_id, SUM(quantity) as votes')
            ->pluck('votes', 'nominee_id');
        $drift = [];

        foreach (Nominee::query()->where('award_category_id', $awardCategoryId)->get([
            'id',
            'votes_count',
        ]) as $nominee) {
            $votes = (int) ($ledger[$nominee->id] ?? 0);

            if ($votes === $nominee->votes_count) {
                continue;
            }

            $drift[$nominee->id] = ['counter' => $nominee->votes_count, 'ledger' => $votes];
            Log::channel('voting')->warning('tally.drift', [
                'award_category_id' => $awardCategoryId,
                'nominee_id' => $nominee->id,
                'counter' => $nominee->votes_count,
                'ledger' => $votes,
                'fixed' => !$dryRun,
            ]);

            if (!$dryRun) {
                Nominee::query()->whereKey($nominee->id)->update(['votes_count' => $votes]);
            }
        }

        return $drift;
    }
}
