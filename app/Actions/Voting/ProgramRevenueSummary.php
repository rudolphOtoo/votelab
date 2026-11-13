<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\TransactionStatus;

final readonly class ProgramRevenueSummary
{
    /**
     * @param  array<string, int>  $transactionsByStatus  TransactionStatus value => count, every status present
     */
    public function __construct(
        public RevenueTotals $credited, // Successful: votes counted, money settled to the organizer
        public RevenueTotals $paidUncredited, // Money received but votes not counted: refund liability
        public array $transactionsByStatus,
    ) {}

    public function countOf(TransactionStatus $status): int
    {
        return $this->transactionsByStatus[$status->value] ?? 0;
    }
}
