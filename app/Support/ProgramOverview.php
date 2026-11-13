<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Reporting\RecordRefundAction;
use App\Actions\Voting\SummarizeProgramRevenueAction;
use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\User;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;

/**
 * The stat tiles on a program's overview (UI Step 3), each shown only to someone allowed to see it:
 * - votes today / total: only when the user may see the counts of every category (public results, or
 *   results.viewHidden), so hidden results never leak through a program total; from the ledger (D-05);
 * - revenue (revenue.view): the vote value credited to the organizer;
 * - pending payments (transactions.view) and refunds waiting (refunds.view).
 */
final class ProgramOverview
{
    /**
     * @return list<array{key: string, label: string, value: string, hint: ?string, tone: ?string}>
     */
    public static function tiles(AwardProgram $program, User $user): array
    {
        $tiles = [];
        $categories = $program->categories()->get();
        $countsVisible = $categories->every(
            static fn(AwardCategory $c): bool => $user->can('viewResults', $c) || $user->can('viewHiddenResults', $c),
        );

        if ($countsVisible) {
            $votes = VoteRecord::query()->where('award_program_id', $program->id);
            $today = now(PublicSchedule::TIMEZONE)->startOfDay()->utc();
            $tiles[] = self::tile(
                'votes_today',
                'Votes today',
                (int) (clone $votes)->where('cast_at', '>=', $today)->sum('quantity'),
                'Since midnight, Accra time',
            );
            $tiles[] = self::tile('votes_total', 'Total votes', (int) $votes->sum('quantity'));
        }

        if ($user->can('viewRevenue', $program)) {
            $tiles[] = [
                'key' => 'revenue',
                'label' => 'Revenue',
                'value' => Money::format(
                    app(SummarizeProgramRevenueAction::class)->execute($program)->credited->votePrice,
                ),
                'hint' => 'Vote value credited to you',
                'tone' => null,
            ];
        }

        if ($user->can('viewTransactions', $program)) {
            $pending = VoteTransaction::query()
                ->where('award_program_id', $program->id)
                ->where('status', TransactionStatus::Pending)
                ->count();
            $tiles[] = self::tile(
                'pending',
                'Pending payments',
                $pending,
                'Waiting for Paystack',
                $pending > 0 ? 'warning' : null,
            );
        }

        if ($user->can('viewRefunds', $program)) {
            $refunds = app(RecordRefundAction::class)->queue($program, $user)->count();
            $tiles[] = self::tile(
                'refunds',
                'Refunds waiting',
                $refunds,
                $refunds > 0 ? 'Refund in Paystack, then record' : null,
                $refunds > 0 ? 'danger' : null,
            );
        }

        return $tiles;
    }

    /**
     * @return array{key: string, label: string, value: string, hint: ?string, tone: ?string}
     */
    private static function tile(
        string $key,
        string $label,
        int $value,
        ?string $hint = null,
        ?string $tone = null,
    ): array {
        return ['key' => $key, 'label' => $label, 'value' => number_format($value), 'hint' => $hint, 'tone' => $tone];
    }
}
