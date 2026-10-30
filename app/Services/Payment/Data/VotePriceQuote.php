<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

/**
 * A priced paid-vote order. Every amount is in pesewas.
 */
final readonly class VotePriceQuote
{
    public function __construct(
        public int $quantity,
        public int $unitPrice,
        public int $netAmount, // quantity x unit price: what the organizer receives
        public int $feeAmount, // gateway fee passed on to the voter
        public int $grossAmount, // what the voter is charged
    ) {}
}
