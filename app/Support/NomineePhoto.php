<?php

declare(strict_types=1);

namespace App\Support;

use finfo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nominee portraits. The upload is checked by its actual content (JPEG, PNG or WebP), size limited, and
 * kept as the original on a private disk; the public portrait is always re-encoded from that original with
 * the organizer's 4:5 crop, as WebP at config('organizations.nominee_photos') size. Re-encoding drops
 * embedded metadata (EXIF location, camera details). Re-cropping later never loses quality.
 *
 * A crop is normalized to the original: x and y are the frame's top-left corner and width its width, all
 * as fractions of the original's width/height; the height follows from the 4:5 shape.
 */
final class NomineePhoto
{
    private const array ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param  array<string, mixed>|null  $crop
     * @return array{avatar_url: string, avatar_variants: array<int, string>, photo_original_path: string, photo_crop: array{x: float, y: float, width: float}}
     *
     * @throws ValidationException
     */
    public static function store(UploadedFile $file, ?array $crop = null): array
    {
        $config = self::config();
        $maxBytes = (int) $config['max_kilobytes'] * 1024;
        $path = $file->getRealPath();

        if ($path === false || $file->getSize() === false || $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'photo' => "The photo must be at most {$config['max_kilobytes']} KB.",
            ]);
        }

        // The file's actual bytes decide its type, not its name or the browser's claim.
        $mime = new finfo(FILEINFO_MIME_TYPE)->file($path);

        if (!is_string($mime) || !array_key_exists($mime, self::ALLOWED)) {
            throw ValidationException::withMessages(['photo' => 'The photo must be a JPEG, PNG or WebP image.']);
        }

        $bytes = (string) file_get_contents($path);
        self::decode($bytes); // Refuse anything GD can't actually read before storing it.

        $originalPath = 'nominee-originals/' . Str::uuid()->toString() . '.' . self::ALLOWED[$mime];
        Storage::disk((string) $config['original_disk'])->put($originalPath, $bytes);

        return self::render($originalPath, $crop);
    }

    /**
     * (Re-)encode the public portrait, and its smaller srcset variants, from the stored original with a crop.
     * File names carry a hash of the crop, so a re-crop writes new files and replaces every URL at once
     * (D-127): a stale variant can never be served next to a new portrait.
     *
     * @param  array<string, mixed>|null  $crop
     * @return array{avatar_url: string, avatar_variants: array<int, string>, photo_original_path: string, photo_crop: array{x: float, y: float, width: float}}
     *
     * @throws ValidationException
     */
    public static function render(string $originalPath, ?array $crop): array
    {
        $config = self::config();
        [$source, $normalized, $base] = self::prepare($originalPath, $crop);
        $public = Storage::disk((string) $config['disk']);
        $public->put(
            "{$base}.webp",
            self::encodeWebp($source, $normalized, (int) $config['width'], (int) $config['height']),
            'public',
        );

        return [
            'avatar_url' => $public->url("{$base}.webp"),
            'avatar_variants' => self::writeVariants($source, $normalized, $base),
            'photo_original_path' => $originalPath,
            'photo_crop' => $normalized,
        ];
    }

    /**
     * Only the smaller variants, for portraits made before they existed (nominees:photo-variants). Same
     * original, same crop, same file names as render() would give.
     *
     * @param  array<string, mixed>|null  $crop
     * @return array<int, string>  width => public URL
     *
     * @throws ValidationException
     */
    public static function variants(string $originalPath, ?array $crop): array
    {
        [$source, $normalized, $base] = self::prepare($originalPath, $crop);

        return self::writeVariants($source, $normalized, $base);
    }

    /**
     * @param  array<string, mixed>|null  $crop
     * @return array{0: GdImage, 1: array{x: float, y: float, width: float}, 2: string}
     *
     * @throws ValidationException
     */
    private static function prepare(string $originalPath, ?array $crop): array
    {
        $config = self::config();
        $source = self::decode((string) Storage::disk((string) $config['original_disk'])->get($originalPath));
        $normalized = NomineePhotoCrop::normalize($crop, imagesx($source), imagesy($source));
        $base =
            'nominees/'
            . pathinfo($originalPath, PATHINFO_FILENAME)
            . '-'
            . substr(sha1((string) json_encode($normalized)), 0, 12);

        return [$source, $normalized, $base];
    }

    /**
     * @param  array{x: float, y: float, width: float}  $crop
     * @return array<int, string>  width => public URL
     */
    private static function writeVariants(GdImage $source, array $crop, string $base): array
    {
        $public = Storage::disk((string) self::config()['disk']);
        $urls = [];

        foreach ((array) self::config()['variant_widths'] as $width) {
            $width = (int) $width;
            $path = "{$base}-{$width}w.webp";
            $public->put($path, self::encodeWebp($source, $crop, $width, (int) round(($width * 5) / 4)), 'public');
            $urls[$width] = $public->url($path);
        }

        return $urls;
    }

    /**
     * The cropped original as a GD image of the given size (for share images).
     *
     * @param  array<string, mixed>|null  $crop
     */
    public static function croppedImage(string $originalPath, ?array $crop, int $width, int $height): GdImage
    {
        $config = self::config();
        $source = self::decode((string) Storage::disk((string) $config['original_disk'])->get($originalPath));

        return NomineePhotoCrop::apply(
            $source,
            NomineePhotoCrop::normalize($crop, imagesx($source), imagesy($source)),
            $width,
            $height,
        );
    }

    /**
     * @param  array{x: float, y: float, width: float}  $crop
     */
    private static function encodeWebp(GdImage $source, array $crop, int $width, int $height): string
    {
        $canvas = NomineePhotoCrop::apply($source, $crop, $width, $height);
        ob_start();
        imagewebp($canvas, null, 82);

        return (string) ob_get_clean();
    }

    private static function decode(string $bytes): GdImage
    {
        $image = $bytes === '' ? false : imagecreatefromstring($bytes);

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be read.']);
        }

        return $image;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        return (array) config('organizations.nominee_photos');
    }
}
