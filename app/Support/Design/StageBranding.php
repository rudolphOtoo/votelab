<?php

declare(strict_types=1);

namespace App\Support\Design;

use App\Models\Organization;

/**
 * An organization's colour on dark surfaces: the stage screen and share images (basalt #121316, D-131).
 */
final class StageBranding
{
    public const string BASALT = '#121316';

    public const string STAGE_GOLD = '#fbbf24';

    private const string WHITE = '#ffffff';

    /**
     * The organization's colour lightened until it reads at 4.5:1 on basalt; VoteLab's stage gold when the
     * organization has no usable colour.
     */
    public static function accent(?Organization $organization): string
    {
        $color = $organization?->settings['brand_color'] ?? null;

        return self::accentFor(is_string($color) ? $color : null);
    }

    /**
     * accent() for a colour that isn't saved yet (the settings preview).
     */
    public static function accentFor(?string $color): string
    {
        $palette = Contrast::isHex($color) ? Branding::fromColor(strtolower((string) $color)) : Branding::votelab();

        if (!$palette->custom) {
            return self::STAGE_GOLD;
        }

        $accent = $palette->brand;

        for ($step = 0; $step < 12 && Contrast::ratio($accent, self::BASALT) < Contrast::AA_TEXT; $step++) {
            $accent = Contrast::mix($accent, self::WHITE, 0.12);
        }

        return Contrast::ratio($accent, self::BASALT) >= Contrast::AA_TEXT ? $accent : self::STAGE_GOLD;
    }

    /**
     * The accent as RGB, for GD (share images).
     *
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    public static function rgb(?Organization $organization): array
    {
        $hex = ltrim(self::accent($organization), '#');

        return [
            max(0, min(255, (int) hexdec(substr($hex, 0, 2)))),
            max(0, min(255, (int) hexdec(substr($hex, 2, 2)))),
            max(0, min(255, (int) hexdec(substr($hex, 4, 2)))),
        ];
    }
}
