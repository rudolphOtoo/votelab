<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats integer minor units for display without going through floats.
 */
final class Money
{
    /**
     * "1", "1.5" or "1.50" cedis as integer pesewas (150), using string arithmetic only (no floats).
     * Null for anything else, including negatives and more than two decimal places.
     */
    public static function parsePesewas(string $input): ?int
    {
        if (preg_match('/^\s*(\d{1,7})(?:\.(\d{1,2}))?\s*$/', $input, $match) !== 1) {
            return null;
        }

        return ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '0', 2, '0');
    }

    public static function format(int $minorUnits, ?string $currency = null): string
    {
        $currency ??= (string) config('voting.currency');
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);

        return sprintf('%s %s%s.%02d', $currency, $sign, number_format(intdiv($absolute, 100)), $absolute % 100);
    }
}
