<?php

declare(strict_types=1);

use App\Services\Payment\PaystackFeeCalculator;

it('prices 25 votes at GHS 1.00: GHS 25.00 subtotal + GHS 0.50 fee = GHS 25.50', function (): void {
    $quote = new PaystackFeeCalculator(feeBps: 195)->quote(quantity: 25, unitPrice: 100);

    expect($quote->quantity)
        ->toBe(25)
        ->and($quote->unitPrice)
        ->toBe(100)
        ->and($quote->subtotal)
        ->toBe(2_500)
        ->and($quote->processingFee)
        ->toBe(50)
        ->and($quote->total)
        ->toBe(2_550);
});

it('charges exactly ceil(subtotal / (1 - rate)), to the pesewa', function (): void {
    $calculator = new PaystackFeeCalculator(feeBps: 195);

    foreach ([1, 7, 49, 50, 99, 100, 101, 333, 1_000, 2_500, 9_999, 123_456, 1_000_000, 4_000_000_000] as $subtotal) {
        $quote = $calculator->quote(quantity: 1, unitPrice: $subtotal);

        // total x (1 - rate) >= subtotal, and one pesewa less would not be.
        expect($quote->total * 9_805)
            ->toBeGreaterThanOrEqual($subtotal * 10_000, "subtotal {$subtotal}")
            ->and(($quote->total - 1) * 9_805)
            ->toBeLessThan($subtotal * 10_000, "subtotal {$subtotal}");
    }
});

it('splits every total into exactly the organizer amount plus the processing fee', function (): void {
    $calculator = new PaystackFeeCalculator(feeBps: 195);

    foreach (range(1, 2_000) as $quantity) {
        $quote = $calculator->quote(quantity: $quantity, unitPrice: 50);

        expect($quote->subtotal)
            ->toBe($quantity * 50)
            ->and($quote->subtotal + $quote->processingFee)
            ->toBe($quote->total);
    }
});

it('never leaves the organizer short when Paystack rounds its fee to the nearest pesewa', function (): void {
    $calculator = new PaystackFeeCalculator(feeBps: 195);

    foreach (range(1, 20_000) as $subtotal) {
        $total = $calculator->quote(quantity: 1, unitPrice: $subtotal)->total;
        $paystackFee = intdiv(($total * 195) + 5_000, 10_000); // round half up

        expect($total - $paystackFee)->toBeGreaterThanOrEqual($subtotal, "subtotal {$subtotal}");
    }
});

it('charges no processing fee when the rate is zero', function (): void {
    $quote = new PaystackFeeCalculator(feeBps: 0)->quote(quantity: 10, unitPrice: 100);

    expect($quote->total)->toBe(1_000)->and($quote->processingFee)->toBe(0);
});

it('rejects non-positive quantities and prices', function (int $quantity, int $unitPrice): void {
    new PaystackFeeCalculator(feeBps: 195)->quote($quantity, $unitPrice);
})->with([
    'zero quantity' => [0, 100],
    'negative quantity' => [-1, 100],
    'zero price' => [10, 0],
])->throws(InvalidArgumentException::class);

it('rejects totals that do not fit the transaction amount columns', function (): void {
    new PaystackFeeCalculator(feeBps: 195)->quote(quantity: 10_000, unitPrice: 1_000_000);
})->throws(InvalidArgumentException::class);

it('rejects impossible rates', function (int $bps): void {
    new PaystackFeeCalculator(feeBps: $bps);
})->with([-1, 10_000])->throws(InvalidArgumentException::class);
