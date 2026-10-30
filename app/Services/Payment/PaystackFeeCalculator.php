<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\Data\VotePriceQuote;
use InvalidArgumentException;

/**
 * Prices paid votes so the organizer receives the full face value ("zero net loss"):
 * the voter pays the Paystack processing fee on top.
 *
 * Rounding rule: the Paystack fee on an amount is estimated as
 * min(cap, ceil(amount x bps / 10000) + flat), always rounded up to the next pesewa.
 * The voter is charged the smallest whole-pesewa amount whose fee-deducted value is at
 * least the face value. Everything is integer arithmetic in pesewas.
 *
 * Ghana (verified 2026-10-30, Paystack support "What are Paystack's transaction charges"):
 * 1.95% for local cards, mobile money and bank transfer; no cap and no flat fee.
 */
final readonly class PaystackFeeCalculator
{
    /** Largest amount the unsigned 32-bit transaction amount columns can hold. */
    public const int MAX_AMOUNT = 4_294_967_295;

    public function __construct(
        private int $feeBps,
        private int $flatFee = 0,
        private ?int $feeCap = null,
    ) {
        if ($feeBps < 0 || $feeBps >= 10_000 || $flatFee < 0 || $feeCap !== null && $feeCap < 0) {
            throw new InvalidArgumentException('Invalid Paystack fee configuration.');
        }
    }

    public static function fromConfig(): self
    {
        $cap = config('voting.paystack_fee.cap');

        return new self(
            feeBps: (int) config('voting.paystack_fee.bps'),
            flatFee: (int) config('voting.paystack_fee.flat'),
            feeCap: is_int($cap) ? $cap : null,
        );
    }

    public function quote(int $quantity, int $unitPrice): VotePriceQuote
    {
        if ($quantity < 1 || $unitPrice < 1) {
            throw new InvalidArgumentException('Quantity and unit price must be positive.');
        }

        if ($quantity > intdiv(self::MAX_AMOUNT, $unitPrice)) {
            throw new InvalidArgumentException('Order total is too large.');
        }

        $net = $quantity * $unitPrice;
        $gross = $this->grossFor($net);

        if ($gross > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Order total is too large.');
        }

        return new VotePriceQuote(
            quantity: $quantity,
            unitPrice: $unitPrice,
            netAmount: $net,
            feeAmount: $gross - $net,
            grossAmount: $gross,
        );
    }

    public function gatewayFeeFor(int $amount): int
    {
        $fee = intdiv(($amount * $this->feeBps) + 9_999, 10_000) + $this->flatFee;

        return $this->feeCap === null ? $fee : min($fee, $this->feeCap);
    }

    /**
     * The least gross g with g - fee(g) >= net. fee() is non-decreasing, so iterating
     * g = net + fee(g) from g = net climbs to the least fixed point in a few steps.
     */
    private function grossFor(int $net): int
    {
        $gross = $net;

        while (($gross - $this->gatewayFeeFor($gross)) < $net) {
            $gross = $net + $this->gatewayFeeFor($gross);
        }

        return $gross;
    }
}
