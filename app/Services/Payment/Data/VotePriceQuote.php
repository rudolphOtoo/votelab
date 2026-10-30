<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

/**
 * The price breakdown a voter sees before paying (L-8). These exact numbers are stored on the
 * transaction and sent to Paystack. Every amount is in pesewas.
 */
final readonly class VotePriceQuote
{
    public function __construct(
        public int $quantity,
        public int $unitPrice,
        public int $subtotal, // quantity x unit price: what the organizer receives
        public int $processingFee, // Paystack fee, paid by the voter
        public int $total, // what the voter is charged
    ) {}
}
