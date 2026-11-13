<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;
use App\Support\Design\StageBranding;
use App\Support\Images\TextStyle;
use App\Support\Images\TextWriter;
use ErrorException;
use GdImage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Campaign images for a nominee: Open Graph (1200×630), square (1080×1080) and WhatsApp Status (1080×1920),
 * with the cropped portrait, name, vote code, category and program/organization names. Never vote counts.
 *
 * Cached on the public photo disk under share/{nominee_id}/; the file name hashes everything drawn, so a
 * new photo, crop, name or code produces a new file, and forget() (called on every nominee change)
 * removes the old ones.
 */
final class ShareImages
{
    public const array FORMATS = ['og' => [1200, 630], 'square' => [1080, 1080], 'story' => [1080, 1920]];

    private const int VERSION = 2; // 2: organization colour and logo (D-131)

    private const array BACKGROUND = [18, 19, 22]; // Stage basalt #121316

    private const array INK = [245, 245, 244];

    private const array MUTED = [168, 162, 158];

    /**
     * The stored image path for a format, rendering it first if it isn't cached.
     */
    public static function path(Nominee $nominee, string $format): string
    {
        if (!array_key_exists($format, self::FORMATS)) {
            throw new InvalidArgumentException("Unknown share image format [{$format}].");
        }

        $nominee->loadMissing(['awardCategory', 'awardProgram.organization']);
        $path = "share/{$nominee->id}/" . self::fingerprint($nominee, $format) . "-{$format}.jpg";
        $disk = self::disk();

        if (!$disk->exists($path)) {
            $disk->put($path, self::render($nominee, $format), 'public');
        }

        return $path;
    }

    public static function forget(int $nomineeId): void
    {
        self::disk()->deleteDirectory("share/{$nomineeId}");
    }

    /**
     * Every cached image of an organization's nominees (its name, logo or colour changed).
     */
    public static function forgetOrganization(int $organizationId): void
    {
        Nominee::query()
            ->where('organization_id', $organizationId)
            ->pluck('id')
            ->each(self::forget(...));
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('organizations.nominee_photos.disk'));
    }

    private static function fingerprint(Nominee $nominee, string $format): string
    {
        return substr(
            sha1((string) json_encode([
                self::VERSION,
                $format,
                $nominee->name,
                $nominee->stage_name,
                $nominee->nominee_code,
                $nominee->photo_original_path,
                $nominee->photo_crop,
                $nominee->awardCategory->name,
                $nominee->awardProgram->title,
                $nominee->awardProgram->organization->name,
                $nominee->awardProgram->organization->logo_path,
                StageBranding::accent($nominee->awardProgram->organization),
            ])),
            0,
            16,
        );
    }

    private static function render(Nominee $nominee, string $format): string
    {
        [$width, $height] = self::FORMATS[$format];
        $canvas = self::canvas($width, $height);
        $layout = match ($format) {
            'story' => ['photo' => [100, 180, 880, 1100], 'text' => [100, 1360, 880], 'sizes' => [64, 108, 40]],
            'square' => ['photo' => [60, 190, 560, 700], 'text' => [660, 300, 380], 'sizes' => [48, 84, 30]],
            default => ['photo' => [60, 65, 400, 500], 'text' => [520, 150, 620], 'sizes' => [48, 84, 30]],
        };

        self::drawPhoto($canvas, $nominee, $layout['photo']);
        self::drawLogo($canvas, $nominee, $width);

        [$tx, $ty, $tw] = $layout['text'];
        [$nameSize, $codeSize, $small] = $layout['sizes'];
        $y = TextWriter::draw(
            $canvas,
            $nominee->displayName(),
            [$tx, $ty, $tw],
            new TextStyle($nameSize, self::INK, bold: true, maxLines: 2),
        );
        $y = TextWriter::draw(
            $canvas,
            $nominee->awardCategory->name,
            [$tx, $y + 16, $tw],
            new TextStyle($small, self::MUTED, maxLines: 2),
        );
        $y = TextWriter::draw(
            $canvas,
            'VOTE CODE',
            [$tx, $y + 40, $tw],
            new TextStyle((int) round($small * 0.8), self::MUTED, bold: true),
        );
        $y = TextWriter::draw(
            $canvas,
            $nominee->nominee_code,
            [$tx, $y + 8, $tw],
            new TextStyle($codeSize, self::accent($nominee), bold: true),
        );
        $y = TextWriter::draw(
            $canvas,
            $nominee->awardProgram->title,
            [$tx, $y + 36, $tw],
            new TextStyle($small, self::INK, bold: true, maxLines: 2),
        );
        TextWriter::draw(
            $canvas,
            $nominee->awardProgram->organization->name,
            [$tx, $y + 8, $tw],
            new TextStyle((int) round($small * 0.85), self::MUTED),
        );

        ob_start();
        imagejpeg($canvas, null, 86);

        return (string) ob_get_clean();
    }

    /**
     * The organization's colour, made readable on the dark background (StageBranding::accent), as RGB.
     *
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    private static function accent(Nominee $nominee): array
    {
        return StageBranding::rgb($nominee->awardProgram->organization);
    }

    /**
     * An uploaded organization logo, top right, fitted in an 80 px box. External logo URLs are never fetched.
     */
    private static function drawLogo(GdImage $canvas, Nominee $nominee, int $width): void
    {
        $path = $nominee->awardProgram->organization->logo_path;

        if (!is_string($path) || !OrganizationLogo::disk()->exists($path)) {
            return;
        }

        try {
            $logo = imagecreatefromstring((string) OrganizationLogo::disk()->get($path));
        } catch (ErrorException) {
            return; // A damaged logo file: draw the image without it.
        }

        if ($logo === false) {
            return;
        }

        $box = 80;
        $scale = min($box / imagesx($logo), $box / imagesy($logo));
        $w = max(1, (int) round(imagesx($logo) * $scale));
        $h = max(1, (int) round(imagesy($logo) * $scale));
        imagecopyresampled($canvas, $logo, $width - 60 - $w, 40, 0, 0, $w, $h, imagesx($logo), imagesy($logo));
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $canvas = $width > 0 && $height > 0 ? imagecreatetruecolor($width, $height) : false;

        if ($canvas === false) {
            throw new InvalidArgumentException('Could not create the share image.');
        }

        imagefill($canvas, 0, 0, TextWriter::color($canvas, self::BACKGROUND));

        return $canvas;
    }

    /**
     * @param  array{int, int, int, int}  $rect  x, y, width, height
     */
    private static function drawPhoto(GdImage $canvas, Nominee $nominee, array $rect): void
    {
        [$x, $y, $width, $height] = $rect;
        if ($nominee->photo_original_path !== null) {
            $photo = NomineePhoto::croppedImage($nominee->photo_original_path, $nominee->photo_crop, $width, $height);
            imagecopy($canvas, $photo, $x, $y, 0, 0, $width, $height);

            return;
        }

        // No stored original (e.g. older nominees): initials on a panel.
        imagefilledrectangle($canvas, $x, $y, $x + $width, $y + $height, TextWriter::color($canvas, [35, 38, 43]));
        $initial = mb_strtoupper(mb_substr($nominee->displayName(), 0, 1));
        $style = new TextStyle((int) ($width * 0.3), self::MUTED, bold: true);
        TextWriter::draw($canvas, $initial, [$x + (int) ($width * 0.38), $y + (int) ($height * 0.35), $width], $style);
    }
}
