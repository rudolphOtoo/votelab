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
 * Stores a nominee portrait: checked by its actual content (JPEG, PNG or WebP; not by file name), size
 * limited, centre-cropped to 4:5 and saved as WebP at config('organizations.nominee_photos') size.
 * Re-encoding also drops any embedded metadata (EXIF location, camera details).
 */
final class NomineePhoto
{
    private const array ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return string The public URL of the stored WebP.
     *
     * @throws ValidationException
     */
    public static function store(UploadedFile $file): string
    {
        $config = (array) config('organizations.nominee_photos');
        $maxBytes = (int) $config['max_kilobytes'] * 1024;
        $path = $file->getRealPath();

        if ($path === false || $file->getSize() === false || $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'photo' => "The photo must be at most {$config['max_kilobytes']} KB.",
            ]);
        }

        // The file's actual bytes decide its type, not its name or the browser's claim.
        $mime = new finfo(FILEINFO_MIME_TYPE)->file($path);

        if (!in_array($mime, self::ALLOWED, true)) {
            throw ValidationException::withMessages(['photo' => 'The photo must be a JPEG, PNG or WebP image.']);
        }

        $source = imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be read.']);
        }

        $webp = self::cropToWebp($source, (int) $config['width'], (int) $config['height']);
        $stored = 'nominees/' . Str::uuid()->toString() . '.webp';
        $disk = Storage::disk((string) $config['disk']);
        $disk->put($stored, $webp, 'public');

        return $disk->url($stored);
    }

    private static function cropToWebp(GdImage $source, int $width, int $height): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $ratio = $width / $height;

        // Largest centred 4:5 window inside the source.
        $cropWidth = min($sourceWidth, (int) round($sourceHeight * $ratio));
        $cropHeight = min($sourceHeight, (int) round($cropWidth / $ratio));
        $x = intdiv($sourceWidth - $cropWidth, 2);
        $y = intdiv($sourceHeight - $cropHeight, 2);

        if ($width < 1 || $height < 1) {
            throw ValidationException::withMessages(['photo' => 'Photo size is misconfigured.']);
        }

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be processed.']);
        }

        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $width, $height, $cropWidth, $cropHeight);

        ob_start();
        imagewebp($canvas, null, 82);

        return (string) ob_get_clean();
    }
}
