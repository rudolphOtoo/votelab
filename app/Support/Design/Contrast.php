<?php

declare(strict_types=1);

namespace App\Support\Design;

use InvalidArgumentException;

/**
 * WCAG 2.2 colour maths for #rrggbb colours: relative luminance, contrast ratio, and mixing.
 */
final class Contrast
{
    public const float AA_TEXT = 4.5;

    public const float AA_LARGE_OR_UI = 3.0;

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    public static function ratio(string $foreground, string $background): float
    {
        $a = self::luminance($foreground);
        $b = self::luminance($background);

        return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);
        $linear = static fn(int $c): float => ($c / 255) <= 0.039_28
            ? ($c / 255) / 12.92
            : ((($c / 255) + 0.055) / 1.055) ** 2.4;

        return (0.2126 * $linear($r)) + (0.7152 * $linear($g)) + (0.0722 * $linear($b));
    }

    /**
     * Mix $hex towards $with by $amount (0 = $hex, 1 = $with).
     */
    public static function mix(string $hex, string $with, float $amount): string
    {
        $a = self::channels($hex);
        $b = self::channels($with);
        $out = '#';

        foreach ([0, 1, 2] as $i) {
            $out .= sprintf('%02x', (int) round(($a[$i] * (1 - $amount)) + ($b[$i] * $amount)));
        }

        return $out;
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $hex): array
    {
        if (!self::isHex($hex)) {
            throw new InvalidArgumentException("Not a #rrggbb colour: {$hex}");
        }

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }
}
