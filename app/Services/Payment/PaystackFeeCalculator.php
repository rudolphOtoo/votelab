<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\Data\VotePriceQuote;
use InvalidArgumentException;

/**
 * Prices paid votes so the voter pays Paystack's processing fee and the organizer receives the
 * full vote price (L-3): total = ceil(subtotal / (1 - rate)), in whole pesewas, integer arithmetic only.
 *
 * With the fee rounded to the nearest pesewa, total - fee(total) >= subtotal always holds, so the
 * organizer is never short.
 *
 * Ghana (verified 2026-10-30, Paystack support "What are Paystack's transaction charges"):
 * 1.95% for local cards, mobile money and bank transfer; no cap and no flat fee.
 */
final readonly class PaystackFeeCalculator
{
    /** Largest amount the unsigned 32-bit transaction amount columns can hold. */
    public const int MAX_AMOUNT = 4_294_967_295;

    private const int BPS_PER_UNIT = 10_000;

    public function __construct(
        private int $feeBps,
    ) {
        if ($feeBps < 0 || $feeBps >= self::BPS_PER_UNIT) {
            throw new InvalidArgumentException('The Paystack fee rate must be between 0 and 9999 basis points.');
        }
    }

    public static function fromConfig(): self
    {
        return new self(feeBps: (int) config('voting.paystack_fee_bps'));
    }

    public function quote(int $quantity, int $unitPrice): VotePriceQuote
    {
        if ($quantity < 1 || $unitPrice < 1) {
            throw new InvalidArgumentException('Quantity and unit price must be positive.');
        }

        if ($quantity > intdiv(self::MAX_AMOUNT, $unitPrice)) {
            throw new InvalidArgumentException('Order total is too large.');
        }

        $subtotal = $quantity * $unitPrice;
        $divisor = self::BPS_PER_UNIT - $this->feeBps;
        $total = intdiv(($subtotal * self::BPS_PER_UNIT) + $divisor - 1, $divisor);

        if ($total > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Order total is too large.');
        }

        return new VotePriceQuote(
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: $subtotal,
            processingFee: $total - $subtotal,
            total: $total,
        );
    }
}
