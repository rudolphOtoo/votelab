<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryStatus;
use App\Events\CategoryStatusChanged;
use App\Exceptions\Admin\StatusChangeRefusedException;
use App\Models\AwardCategory;
use App\Models\CategoryStatusTransition;
use App\Models\User;
use App\Support\Audit;
use App\Support\LeaderboardCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The one way to open, embargo, unembargo or close a category (D-04). Reveal has its own action.
 *
 * Authorized against the stored status (AwardCategoryPolicy::changeStatus), then applied under a row lock
 * that re-checks the move, so two organizers changing status at once can't both succeed from the same
 * starting point. Closing stamps closed_at (the start of the close grace window, D-85). Each change is
 * recorded in category_status_transitions and the audit log. After commit: both leaderboard cache entries
 * are cleared (D-68), the cache is rebuilt if results just became public (D-69), and
 * CategoryStatusChanged is dispatched.
 */
final readonly class TransitionCategoryStatusAction
{
    /**
     * @throws AuthorizationException
     * @throws StatusChangeRefusedException
     */
    public function execute(
        AwardCategory $category,
        CategoryStatus $to,
        User $actor,
        ?string $reason = null,
    ): AwardCategory {
        if ($to === CategoryStatus::Revealed) {
            throw StatusChangeRefusedException::useReveal();
        }

        $current = AwardCategory::query()->findOrFail($category->id);
        Gate::forUser($actor)->authorize('changeStatus', [$current, $to]);

        [$updated, $from] = DB::transaction(static function () use ($category, $to, $actor, $reason): array {
            $locked = AwardCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (!$from->canTransitionTo($to)) {
                throw StatusChangeRefusedException::notAllowed($from, $to);
            }

            $locked->forceFill([
                'status' => $to,
                'closed_at' => $to === CategoryStatus::Closed ? now() : $locked->closed_at,
            ])->save();

            CategoryStatusTransition::query()->create([
                'award_category_id' => $locked->id,
                'user_id' => $actor->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
            ]);

            DB::afterCommit(static function () use ($locked): void {
                LeaderboardCache::purge($locked->id);
                LeaderboardCache::warm($locked->id);
            });

            return [$locked, $from];
        });

        Audit::record(
            'category.status_changed',
            $actor,
            [
                'award_category_id' => $updated->id,
                'award_program_id' => $updated->award_program_id,
                'reason' => $reason,
            ],
            ['status' => $from->value],
            ['status' => $to->value],
        );

        CategoryStatusChanged::dispatch($updated, $from, $to, $actor->id);

        return $updated;
    }
}
