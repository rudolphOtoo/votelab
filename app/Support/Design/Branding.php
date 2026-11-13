<?php

declare(strict_types=1);

namespace App\Support\Design;

use App\Models\Organization;

/**
 * Per-organization theming of public pages (and, later, share images). An organization's primary colour
 * (`settings.brand_color`, #rrggbb) is used when a readable on-colour exists: white or near-black text on it must
 * reach 4.5:1. Otherwise — or with no colour — the VoteLab brand is used. Text in the brand colour on the page
 * background and on the brand tint is darkened until it reaches 4.5:1.
 */
final class Branding
{
    public const string VOTELAB_BRAND = '#0e6b5e';

    private const string WHITE = '#ffffff';

    private const string INK = '#18181b';

    private const string CANVAS = '#faf9f6';

    public static function for(?Organization $organization): BrandPalette
    {
        $color = $organization?->settings['brand_color'] ?? null;

        return Contrast::isHex($color) ? self::fromColor(strtolower((string) $color)) : self::votelab();
    }

    public static function fromColor(string $brand): BrandPalette
    {
        $foreground = self::readableOn($brand);

        if ($foreground === null) {
            return self::votelab();
        }

        $hover = Contrast::mix($brand, $foreground === self::WHITE ? '#000000' : self::WHITE, 0.12);
        $subtle = Contrast::mix($brand, self::WHITE, 0.9);

        return new BrandPalette(
            brand: $brand,
            hover: Contrast::ratio($foreground, $hover) >= Contrast::AA_TEXT ? $hover : $brand,
            foreground: $foreground,
            text: self::readableText($brand, $subtle),
            subtle: $subtle,
            custom: $brand !== self::VOTELAB_BRAND,
        );
    }

    /**
     * Why an organization's colour isn't used, for the settings preview; null when it is (or none is set).
     */
    public static function fallbackReason(?string $color): ?string
    {
        if (!Contrast::isHex($color)) {
            return null;
        }

        return self::fromColor(strtolower((string) $color))->custom
        || strtolower((string) $color) === self::VOTELAB_BRAND
            ? null
            : 'Neither white nor dark text is readable on this colour, so VoteLab\'s teal is used instead. Try a darker or lighter shade.';
    }

    public static function votelab(): BrandPalette
    {
        return new BrandPalette(self::VOTELAB_BRAND, '#0b574c', self::WHITE, self::VOTELAB_BRAND, '#e7f2ef', false);
    }

    private static function readableOn(string $brand): ?string
    {
        foreach ([self::WHITE, self::INK] as $candidate) {
            if (Contrast::ratio($candidate, $brand) >= Contrast::AA_TEXT) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Brand-coloured text (links, brand badges): darkened until it reads at 4.5:1 on both the page and the
     * brand's own tint.
     */
    private static function readableText(string $brand, string $subtle): string
    {
        $readable = static fn(string $text): bool => (
            min(Contrast::ratio($text, self::CANVAS), Contrast::ratio($text, $subtle)) >= Contrast::AA_TEXT
        );
        $text = $brand;

        for ($step = 0; $step < 12 && !$readable($text); $step++) {
            $text = Contrast::mix($text, '#000000', 0.1);
        }

        return $readable($text) ? $text : self::INK;
    }
}
