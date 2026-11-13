<?php

declare(strict_types=1);

namespace Database\Seeders\Showcase;

use App\Models\Nominee;
use App\Support\NomineePhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Placeholder images for the local showcase: abstract head-and-shoulders silhouettes and a geometric logo, drawn
 * with GD. No photographs, no real people, nothing downloaded. Each image is written to a temporary file and
 * handed over as an upload, so it goes through the same validation and re-encoding as a real one.
 */
final class ShowcaseArt
{
    /**
     * Through the real pipeline: private original, 800×1000 WebP portrait and every srcset variant.
     */
    public static function attachPortrait(Nominee $nominee, int $seed): void
    {
        $upload = self::portrait($seed);
        $nominee->forceFill(NomineePhoto::store($upload))->save();
        $temporary = $upload->getRealPath();

        if ($temporary !== false && is_file($temporary)) {
            unlink($temporary);
        }
    }

    public static function portrait(int $seed): UploadedFile
    {
        $hue = ($seed * 47) % 360;
        $image = self::canvas(800, 1_000);

        self::verticalGradient($image, self::hsl($hue, 0.38, 0.30), self::hsl(($hue + 30) % 360, 0.45, 0.18));
        $figure = self::colour($image, self::hsl($hue, 0.30, 0.66));
        // Shoulders, neck, head.
        imagefilledellipse($image, 400, 1_020, 640, 520, $figure);
        imagefilledrectangle($image, 350, 560, 450, 780, $figure);
        imagefilledellipse($image, 400, 430, 300 + (($seed % 3) * 14), 360, $figure);

        return self::upload($image, "showcase-portrait-{$seed}");
    }

    public static function logo(int $hue): UploadedFile
    {
        $image = self::canvas(512, 512);
        imagealphablending($image, false);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        imagefilledellipse($image, 256, 256, 500, 500, self::colour($image, self::hsl($hue, 0.62, 0.32)));
        imagefilledpolygon($image, self::star(256, 256, 190, 80), self::colour($image, [255, 255, 255]));

        return self::upload($image, 'showcase-logo');
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD could not create a canvas.');
        }

        imagesavealpha($image, true);

        return $image;
    }

    /**
     * @param  array{int, int, int}  $top
     * @param  array{int, int, int}  $bottom
     */
    private static function verticalGradient(GdImage $image, array $top, array $bottom): void
    {
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y += 4) {
            $t = $y / $height;
            $mixed = [
                (int) round($top[0] + (($bottom[0] - $top[0]) * $t)),
                (int) round($top[1] + (($bottom[1] - $top[1]) * $t)),
                (int) round($top[2] + (($bottom[2] - $top[2]) * $t)),
            ];
            imagefilledrectangle($image, 0, $y, imagesx($image), $y + 3, self::colour($image, $mixed));
        }
    }

    /**
     * @return list<int>
     */
    private static function star(int $cx, int $cy, int $outer, int $inner): array
    {
        $points = [];

        for ($i = 0; $i < 10; $i++) {
            $radius = ($i % 2) === 0 ? $outer : $inner;
            $angle = deg2rad(-90 + ($i * 36));
            $points[] = (int) round($cx + ($radius * cos($angle)));
            $points[] = (int) round($cy + ($radius * sin($angle)));
        }

        return $points;
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function colour(GdImage $image, array $rgb): int
    {
        return (int) imagecolorallocate(
            $image,
            max(0, min(255, $rgb[0])),
            max(0, min(255, $rgb[1])),
            max(0, min(255, $rgb[2])),
        );
    }

    /**
     * @return array{int, int, int}
     */
    private static function hsl(int $hue, float $saturation, float $lightness): array
    {
        $chroma = (1 - abs((2 * $lightness) - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - ($chroma / 2);

        [$r, $g, $b] = match (intdiv($hue, 60) % 6) {
            0 => [$chroma, $x, 0.0],
            1 => [$x, $chroma, 0.0],
            2 => [0.0, $chroma, $x],
            3 => [0.0, $x, $chroma],
            4 => [$x, 0.0, $chroma],
            default => [$chroma, 0.0, $x],
        };

        return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
    }

    private static function upload(GdImage $image, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vl-showcase-');

        if ($path === false || !imagepng($image, $path)) {
            throw new RuntimeException('Could not write a showcase image.');
        }

        return new UploadedFile($path, "{$name}.png", 'image/png', null, true);
    }
}
