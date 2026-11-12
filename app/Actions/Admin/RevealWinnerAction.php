<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Events\CategoryStatusChanged;
use App\Events\WinnerRevealed;
use App\Exceptions\Admin\PendingPaymentsException;
use App\Exceptions\Admin\StatusChangeRefusedException;
use App\Models\AwardCategory;
use App\Models\CategoryStatusTransition;
use App\Models\User;
use App\Models\VoteTransaction;
use App\Support\Audit;
use App\Support\LeaderboardCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Closed → Revealed, once, with the official count.
 *
 * - Refuses while payments started before the close are still awaiting confirmation (they could still be
 *   credited inside the close grace window, D-85), unless $overridePendingPayments is set; overridden
 *   payments become paid_uncredited (refunds) when confirmed, because a revealed category credits nothing.
 * - Recounts every nominee's votes_count from vote_records (the ledger) before revealing, and logs drift.
 * - Idempotent: revealing a revealed category returns the same podium and changes nothing.
 * - After commit: both leaderboard cache entries are cleared and rebuilt from the recount (D-68/D-69),
 *   then CategoryStatusChanged and WinnerRevealed are dispatched for the stage screen.
 */
final readonly class RevealWinnerAction
{
    /**
     * @throws AuthorizationException
     * @throws PendingPaymentsException
     * @throws StatusChangeRefusedException
     */
    public function execute(AwardCategory $category, User $actor, bool $overridePendingPayments = false): RevealResult
    {
        $current = AwardCategory::query()->findOrFail($category->id);

        if ($current->status === CategoryStatus::Revealed) {
            Gate::forUser($actor)->authorize('viewResults', $current);

            return RevealResult::forCategory($current, newlyRevealed: false);
        }

        Gate::forUser($actor)->authorize('changeStatus', [$current, CategoryStatus::Revealed]);

        $pending = DB::transaction(static function () use ($category, $actor, $overridePendingPayments): ?int {
            $locked = AwardCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CategoryStatus::Revealed) {
                return null; // Revealed by someone else while we waited for the lock.
            }

            if ($locked->status !== CategoryStatus::Closed) {
                throw StatusChangeRefusedException::notAllowed($locked->status, CategoryStatus::Revealed);
            }

            $pending = self::pendingPaymentsCount($locked);

            if ($pending > 0 && !$overridePendingPayments) {
                throw new PendingPaymentsException($pending);
            }

            RecountCategoryTallies::apply($locked->id, $actor);
            $locked->forceFill(['status' => CategoryStatus::Revealed])->save();

            CategoryStatusTransition::query()->create([
                'award_category_id' => $locked->id,
                'user_id' => $actor->id,
                'from_status' => CategoryStatus::Closed->value,
                'to_status' => CategoryStatus::Revealed->value,
                'reason' => $pending > 0 ? "revealed_with_{$pending}_pending_payments" : null,
            ]);

            DB::afterCommit(static function () use ($locked): void {
                LeaderboardCache::purge($locked->id);
                LeaderboardCache::warm($locked->id);
            });

            return $pending;
        });

        $revealed = AwardCategory::query()->findOrFail($category->id);

        if ($pending === null) {
            return RevealResult::forCategory($revealed, newlyRevealed: false);
        }

        $result = RevealResult::forCategory($revealed, newlyRevealed: true, pendingOverridden: $pending);

        Audit::record(
            'category.revealed',
            $actor,
            $revealed,
            [
                'before' => ['status' => CategoryStatus::Closed->value],
                'after' => ['status' => CategoryStatus::Revealed->value, 'winner_ids' => $result->winnerIds],
            ],
            ['pending_payments_overridden' => $pending],
        );

        CategoryStatusChanged::dispatch($revealed, CategoryStatus::Closed, CategoryStatus::Revealed, $actor->id);
        WinnerRevealed::dispatch($result);

        return $result;
    }

    /**
     * Payments that could still be credited if the reveal waited, and so block it (the 2d screen shows the
     * count): still `pending` (an `expired` checkout was swept as unpaid), started at or before the close, and
     * started within the last close_grace_minutes (an older unfinished checkout counts as abandoned) — and
     * only while the grace window since the close is still running. Past the cap nothing blocks.
     */
    public static function pendingPaymentsCount(AwardCategory $category): int
    {
        if ($category->closed_at === null) {
            return 0;
        }

        $grace = (int) config('voting.close_grace_minutes');
        $now = now();

        if ($now->greaterThanOrEqualTo($category->closed_at->addMinutes($grace))) {
            return 0;
        }

        return VoteTransaction::query()
            ->where('award_category_id', $category->id)
            ->where('status', TransactionStatus::Pending)
            ->where('created_at', '<=', $category->closed_at)
            ->where('created_at', '>', $now->subMinutes($grace))
            ->count();
    }
}
