<?php

declare(strict_types=1);

namespace App\Support;

use GdImage;
use Illuminate\Validation\ValidationException;

/**
 * The 4:5 crop of a nominee photo, normalized to the original: x and y are the frame's top-left corner and
 * width its width, as fractions of the original's width/height; the height follows from the 4:5 shape.
 */
final class NomineePhotoCrop
{
    /**
     * Clamp a requested crop into the original; with no crop, the largest centred 4:5 frame.
     *
     * @param  array<string, mixed>|null  $crop
     * @return array{x: float, y: float, width: float}
     */
    public static function normalize(?array $crop, int $sourceWidth, int $sourceHeight): array
    {
        $ratio = 4 / 5;
        // Largest frame width (as a fraction of the source width) whose 4:5 height still fits.
        $maxWidth = min(1.0, ($sourceHeight * $ratio) / max(1, $sourceWidth));
        $width = is_numeric($crop['width'] ?? null) ? (float) $crop['width'] : $maxWidth;
        $width = max($maxWidth * 0.2, min($maxWidth, $width));
        $heightFraction = (($width * $sourceWidth) / $ratio) / max(1, $sourceHeight);

        $x = is_numeric($crop['x'] ?? null) ? (float) $crop['x'] : (1 - $width) / 2;
        $y = is_numeric($crop['y'] ?? null) ? (float) $crop['y'] : (1 - $heightFraction) / 2;

        return [
            'x' => round(max(0.0, min(1 - $width, $x)), 5),
            'y' => round(max(0.0, min(1 - $heightFraction, $y)), 5),
            'width' => round($width, 5),
        ];
    }

    /**
     * @param  array{x: float, y: float, width: float}  $crop
     */
    public static function apply(GdImage $source, array $crop, int $width, int $height): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw ValidationException::withMessages(['photo' => 'Photo size is misconfigured.']);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropWidth = max(1, (int) round($crop['width'] * $sourceWidth));
        $cropHeight = max(1, min($sourceHeight, (int) round(($cropWidth * 5) / 4)));
        $x = (int) round($crop['x'] * $sourceWidth);
        $y = (int) round($crop['y'] * $sourceHeight);

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be processed.']);
        }

        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $width, $height, $cropWidth, $cropHeight);

        return $canvas;
    }
}
