<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use Illuminate\Database\Eloquent\Collection;

/**
 * Presentation rules for the public category page (CategoryShow).
 */
final class CategoryPage
{
    /**
     * Once the winner is announced: most votes first, ties keeping their display order. Only call this with
     * public standings; while voting is open, cards keep their place so they don't move under a voter's finger.
     *
     * @param  Collection<int, Nominee>  $nominees
     * @param  array<int, int>  $standings  nominee id => votes
     * @return Collection<int, Nominee>
     */
    public static function byVotes(Collection $nominees, array $standings): Collection
    {
        return $nominees->sortBy([
            static fn(Nominee $a, Nominee $b): int => ($standings[$b->id] ?? 0) <=> ($standings[$a->id] ?? 0),
        ])->values();
    }

    /**
     * Title, meta description and canonical URL: the nominee's deep link when one is selected. Never counts.
     *
     * @return array{title: string, description: string, canonical: string}
     */
    public static function head(AwardProgram $program, AwardCategory $category, ?Nominee $selected): array
    {
        if ($selected !== null) {
            return [
                'title' => "Vote for {$selected->displayName()} · {$category->name}",
                'description' => "Vote for {$selected->displayName()} (code {$selected->nominee_code}) in {$category->name} at {$program->title}, with mobile money or card.",
                'canonical' => route('public.nominee', [$program->organization, $program, $selected->nominee_code]),
            ];
        }

        return [
            'title' => "{$category->name} · {$program->title}",
            'description' => "See the nominees for {$category->name} at {$program->title} and vote with mobile money or card.",
            'canonical' => route('public.category', [$program->organization, $program, $category]),
        ];
    }
}
