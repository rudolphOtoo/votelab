<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AwardCategory;
use App\Models\Nominee;

/**
 * What the stage screen shows when a category is revealed.
 */
final readonly class RevealResult
{
    /**
     * @param  list<array{nominee_id: int, name: string, votes: int}>  $podium  Top three by votes (ties share a rank's votes)
     * @param  list<int>  $winnerIds  Every nominee with the top vote count (more than one on a tie)
     */
    public function __construct(
        public int $awardCategoryId,
        public array $podium,
        public array $winnerIds,
        public bool $newlyRevealed,
        public int $pendingPaymentsOverridden = 0,
    ) {}

    /**
     * The podium and winners from the category's current counters (call after the recount).
     */
    public static function forCategory(AwardCategory $category, bool $newlyRevealed, int $pendingOverridden = 0): self
    {
        $ranked = Nominee::query()
            ->where('award_category_id', $category->id)
            ->orderByDesc('votes_count')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id', 'name', 'stage_name', 'votes_count']);

        $top = (int) ($ranked->first()->votes_count ?? 0);
        $podium = [];
        $winnerIds = [];

        foreach ($ranked as $position => $nominee) {
            if ($position < 3) {
                $podium[] = [
                    'nominee_id' => $nominee->id,
                    'name' => $nominee->displayName(),
                    'votes' => $nominee->votes_count,
                ];
            }

            if ($top > 0 && $nominee->votes_count === $top) {
                $winnerIds[] = $nominee->id;
            }
        }

        return new self(
            awardCategoryId: $category->id,
            podium: $podium,
            winnerIds: $winnerIds,
            newlyRevealed: $newlyRevealed,
            pendingPaymentsOverridden: $pendingOverridden,
        );
    }
}
