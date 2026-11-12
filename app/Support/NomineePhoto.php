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
     * @return array{avatar_url: string, photo_original_path: string, photo_crop: array{x: float, y: float, width: float}}
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
     * (Re-)encode the public portrait from the stored original with a crop.
     *
     * @param  array<string, mixed>|null  $crop
     * @return array{avatar_url: string, photo_original_path: string, photo_crop: array{x: float, y: float, width: float}}
     *
     * @throws ValidationException
     */
    public static function render(string $originalPath, ?array $crop): array
    {
        $config = self::config();
        $source = self::decode((string) Storage::disk((string) $config['original_disk'])->get($originalPath));
        $normalized = NomineePhotoCrop::normalize($crop, imagesx($source), imagesy($source));
        $webp = self::encodeWebp($source, $normalized, (int) $config['width'], (int) $config['height']);

        $public = Storage::disk((string) $config['disk']);
        $derived =
            'nominees/'
            . pathinfo($originalPath, PATHINFO_FILENAME)
            . '-'
            . substr(sha1((string) json_encode($normalized)), 0, 12)
            . '.webp';
        $public->put($derived, $webp, 'public');

        return [
            'avatar_url' => $public->url($derived),
            'photo_original_path' => $originalPath,
            'photo_crop' => $normalized,
        ];
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
