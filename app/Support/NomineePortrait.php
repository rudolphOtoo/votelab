<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;

/**
 * Which portrait file to send (D-127): the 800×1000 portrait plus its 400 and 240 px copies, when they exist.
 */
final class NomineePortrait
{
    /**
     * Every size the portrait exists in, for `srcset`. Null when only the 800×1000 portrait exists (variants
     * not generated yet) or there is no photo.
     */
    public static function srcset(Nominee $nominee): ?string
    {
        $variants = self::variants($nominee);

        if ($nominee->avatar_url === null || $variants === []) {
            return null;
        }

        $candidates = [(int) config('organizations.nominee_photos.width') => $nominee->avatar_url] + $variants;
        ksort($candidates);

        return implode(', ', array_map(
            static fn(int $width, string $url): string => "{$url} {$width}w",
            array_keys($candidates),
            $candidates,
        ));
    }

    /**
     * The smallest copy there is (for thumbnails), or the full portrait.
     */
    public static function smallest(Nominee $nominee): ?string
    {
        $variants = self::variants($nominee);
        ksort($variants);

        return $variants === [] ? $nominee->avatar_url : reset($variants);
    }

    /**
     * @return array<int, string>  width => URL
     */
    private static function variants(Nominee $nominee): array
    {
        $variants = [];

        foreach ((array) ($nominee->avatar_variants ?? []) as $width => $url) {
            $variants[(int) $width] = (string) $url;
        }

        return $variants;
    }
}
