<?php

declare(strict_types=1);

namespace App\Support\Images;

use GdImage;

/**
 * Wrapped TrueType text on a GD image, in the app's typeface (Instrument Sans, resources/fonts, OFL).
 * Text that still doesn't fit after wrapping is shortened with an ellipsis.
 */
final class TextWriter
{
    /**
     * Draw inside a box given as [x, top y, max width]; returns the y just below the text.
     *
     * @param  array{int, int, int}  $box
     */
    public static function draw(GdImage $canvas, string $text, array $box, TextStyle $style): int
    {
        [$x, $y, $maxWidth] = $box;
        $font = self::font($style->bold);
        $color = self::color($canvas, $style->rgb);
        $lineHeight = (int) round($style->size * 1.2);

        foreach (self::wrap($text, $style, $maxWidth) as $line) {
            $y += $lineHeight;
            imagettftext($canvas, $style->size, 0, $x, $y, $color, $font, $line);
        }

        return $y + (int) round($style->size * 0.25);
    }

    /**
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    public static function color(GdImage $canvas, array $rgb): int
    {
        return (int) imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, TextStyle $style, int $maxWidth): array
    {
        $font = self::font($style->bold);
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $current = '';

        foreach ($words === false ? [] : $words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($current === '' || self::width($candidate, $font, $style->size) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        $lines[] = $current;
        $lines = array_slice($lines, 0, max(1, $style->maxLines));
        $last = count($lines) - 1;

        while (self::width($lines[$last], $font, $style->size) > $maxWidth && mb_strlen($lines[$last]) > 1) {
            $lines[$last] = rtrim(mb_substr($lines[$last], 0, -2)) . '…';
        }

        return $lines;
    }

    private static function width(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : abs($box[2] - $box[0]);
    }

    private static function font(bool $bold): string
    {
        return resource_path('fonts/InstrumentSans-' . ($bold ? 'Bold' : 'Regular') . '.ttf');
    }
}
