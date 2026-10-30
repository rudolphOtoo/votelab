<?php

declare(strict_types=1);

use App\Services\Payment\PaystackFeeCalculator;

function ghanaCalculator(int $bps = 195, int $flat = 0, ?int $cap = null): PaystackFeeCalculator
{
    return new PaystackFeeCalculator(feeBps: $bps, flatFee: $flat, feeCap: $cap);
}

it('prices 25 votes at GHS 1.00 so the organizer nets exactly GHS 25.00', function (): void {
    $quote = ghanaCalculator()->quote(quantity: 25, unitPrice: 100);

    expect($quote->quantity)
        ->toBe(25)
        ->and($quote->unitPrice)
        ->toBe(100)
        ->and($quote->netAmount)
        ->toBe(2_500)
        ->and($quote->grossAmount)
        ->toBe(2_550)
        ->and($quote->feeAmount)
        ->toBe(50);
});

it('estimates the Paystack fee rounded up to the next pesewa', function (): void {
    $calculator = ghanaCalculator();

    expect($calculator->gatewayFeeFor(2_500))
        ->toBe(49) // 48.75
        ->and($calculator->gatewayFeeFor(2_550))
        ->toBe(50) // 49.725
        ->and($calculator->gatewayFeeFor(100))
        ->toBe(2); // 1.95
});

it('always leaves the organizer at least the face value, with the smallest possible gross', function (): void {
    $calculator = ghanaCalculator();

    foreach ([1, 7, 49, 50, 99, 100, 101, 333, 1_000, 2_500, 9_999, 123_456, 1_000_000] as $net) {
        $quote = $calculator->quote(quantity: $net, unitPrice: 1);

        expect($quote->grossAmount - $calculator->gatewayFeeFor($quote->grossAmount))
            ->toBeGreaterThanOrEqual($net, "net {$net}")
            ->and($quote->grossAmount - 1 - $calculator->gatewayFeeFor($quote->grossAmount - 1))
            ->toBeLessThan($net, "gross for net {$net} is not minimal")
            ->and($quote->feeAmount)
            ->toBe($quote->grossAmount - $quote->netAmount);
    }
});

it('respects a flat fee and a fee cap when configured', function (): void {
    $capped = ghanaCalculator(bps: 150, flat: 10, cap: 200)->quote(quantity: 1_000, unitPrice: 100);

    expect($capped->netAmount)
        ->toBe(100_000)
        ->and($capped->feeAmount)
        ->toBe(200)
        ->and($capped->grossAmount)
        ->toBe(100_200);
});

it('charges no surcharge when the fee rate is zero', function (): void {
    $quote = ghanaCalculator(bps: 0)->quote(quantity: 10, unitPrice: 100);

    expect($quote->grossAmount)->toBe(1_000)->and($quote->feeAmount)->toBe(0);
});

it('rejects non-positive quantities and prices', function (int $quantity, int $unitPrice): void {
    ghanaCalculator()->quote($quantity, $unitPrice);
})->with([
    'zero quantity' => [0, 100],
    'negative quantity' => [-1, 100],
    'zero price' => [10, 0],
])->throws(InvalidArgumentException::class);

it('rejects totals that do not fit the transaction amount columns', function (): void {
    ghanaCalculator()->quote(quantity: 10_000, unitPrice: 1_000_000);
})->throws(InvalidArgumentException::class);
