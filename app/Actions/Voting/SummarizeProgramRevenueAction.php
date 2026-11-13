<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\TransactionStatus;
use App\Models\AwardProgram;
use App\Models\VoteTransaction;

/**
 * Per-program invoicing totals straight from the transactions ledger, in one grouped query.
 */
final readonly class SummarizeProgramRevenueAction
{
    public function execute(AwardProgram $program): ProgramRevenueSummary
    {
        $rows = VoteTransaction::query()
            ->toBase()
            ->where('award_program_id', $program->id)
            ->groupBy('status')
            ->selectRaw('status, count(*) as transactions, sum(quantity) as votes')
            ->selectRaw(
                'sum(net_amount) as vote_price, sum(processing_fee_pesewas) as processing_fees, sum(gross_amount) as gross',
            )
            ->get()
            ->keyBy('status');

        $totals = static function (TransactionStatus $status) use ($rows): RevenueTotals {
            $row = $rows->get($status->value);

            return $row === null
                ? new RevenueTotals()
                : new RevenueTotals(
                    transactions: (int) $row->transactions,
                    votes: (int) $row->votes,
                    votePrice: (int) $row->vote_price,
                    processingFees: (int) $row->processing_fees,
                    gross: (int) $row->gross,
                );
        };

        $counts = [];

        foreach (TransactionStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows->get($status->value)->transactions ?? 0);
        }

        return new ProgramRevenueSummary(
            credited: $totals(TransactionStatus::Successful),
            paidUncredited: $totals(TransactionStatus::PaidUncredited),
            transactionsByStatus: $counts,
        );
    }
}
