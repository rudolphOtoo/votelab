<?php

declare(strict_types=1);

namespace App\Actions\Voting;

/**
 * Money totals for a set of transactions, in pesewas. votePrice + processingFees = gross.
 */
final readonly class RevenueTotals
{
    public function __construct(
        public int $transactions = 0,
        public int $votes = 0,
        public int $votePrice = 0, // Owed to the organizer
        public int $processingFees = 0, // Paid by voters to cover Paystack
        public int $gross = 0, // Charged to voters
    ) {}
}
