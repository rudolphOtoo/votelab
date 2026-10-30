<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats integer minor units for display without going through floats.
 */
final class Money
{
    public static function format(int $minorUnits, ?string $currency = null): string
    {
        $currency ??= (string) config('voting.currency');
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);

        return sprintf('%s %s%s.%02d', $currency, $sign, number_format(intdiv($absolute, 100)), $absolute % 100);
    }
}
