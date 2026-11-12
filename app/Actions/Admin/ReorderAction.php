<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a category within its program, or a nominee within its category, one place up or down (buttons
 * rather than drag, so it works by keyboard and on phones). Orders are renumbered 1..n so swaps stay exact.
 */
final readonly class ReorderAction
{
    /**
     * @throws AuthorizationException
     */
    public function category(AwardCategory $category, User $actor, int $direction): void
    {
        Gate::forUser($actor)->authorize('reorder', $category);
        self::move(
            AwardCategory::query()->where('award_program_id', $category->award_program_id),
            $category,
            $direction,
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function nominee(Nominee $nominee, User $actor, int $direction): void
    {
        Gate::forUser($actor)->authorize('reorder', $nominee);
        self::move(Nominee::query()->where('award_category_id', $nominee->award_category_id), $nominee, $direction);
    }

    /**
     * @template TModel of Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $siblings
     */
    private static function move($siblings, Model $item, int $direction): void
    {
        DB::transaction(static function () use ($siblings, $item, $direction): void {
            $ids = $siblings->orderBy('display_order')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $ids = array_values($ids);
            $index = array_search($item->getKey(), $ids, true);

            if (!is_int($index)) {
                return;
            }

            $target = $index + ($direction < 0 ? -1 : 1);

            if ($target < 0 || $target >= count($ids)) {
                return;
            }

            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

            foreach ($ids as $position => $id) {
                $item
                    ->newQuery()
                    ->whereKey($id)
                    ->update(['display_order' => $position + 1]);
            }
        });
    }
}
