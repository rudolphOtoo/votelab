<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AwardProgram;
use App\Support\LeaderboardCache;

/**
 * Recounts every category of the given programs from the ledger (RecountCategoryTallies), fixing counters
 * and refreshing leaderboards unless $dryRun. Used by votes:rebuild-tallies.
 */
final class TallyAudit
{
    /**
     * @param  iterable<AwardProgram>  $programs
     * @return list<array{int, string, int, int, int, string}>  program, category, nominee, counter, ledger, action
     */
    public static function run(iterable $programs, bool $dryRun): array
    {
        $rows = [];

        foreach ($programs as $program) {
            foreach ($program->categories()->get() as $category) {
                $drift = RecountCategoryTallies::apply($category->id, null, $dryRun);

                if ($drift !== [] && !$dryRun) {
                    LeaderboardCache::purge($category->id);
                    LeaderboardCache::warm($category->id);
                }

                foreach ($drift as $nomineeId => $values) {
                    $rows[] = [
                        $program->id,
                        $category->name,
                        $nomineeId,
                        $values['counter'],
                        $values['ledger'],
                        $dryRun ? 'reported' : 'fixed',
                    ];
                }
            }
        }

        return $rows;
    }
}
