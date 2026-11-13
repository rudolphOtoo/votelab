<?php

declare(strict_types=1);

namespace App\Support;

use ErrorException;
use finfo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organization logos (D-131). The upload is checked by its actual content (JPEG, PNG or WebP), size limited,
 * decoded and re-encoded as WebP no larger than 512 px on its longest side, keeping transparency. Re-encoding
 * drops embedded metadata (EXIF location, camera details); the uploaded file itself is never kept.
 */
final class OrganizationLogo
{
    public const int MAX_KILOBYTES = 2_048;

    public const int MAX_SIDE = 512;

    private const array ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{logo_url: string, logo_path: string}
     *
     * @throws ValidationException
     */
    public static function store(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $size = $file->getSize();

        if ($path === false || $size === false || $size > (self::MAX_KILOBYTES * 1024)) {
            throw ValidationException::withMessages(['logo' => 'The logo must be at most 2 MB.']);
        }

        $mime = new finfo(FILEINFO_MIME_TYPE)->file($path);

        if (!is_string($mime) || !in_array($mime, self::ALLOWED, true)) {
            throw ValidationException::withMessages(['logo' => 'The logo must be a JPEG, PNG or WebP image.']);
        }

        try {
            $source = imagecreatefromstring((string) file_get_contents($path));
        } catch (ErrorException) {
            $source = false; // GD warns on damaged data; Laravel turns the warning into an exception.
        }

        if ($source === false) {
            throw ValidationException::withMessages(['logo' => "That image couldn't be read. Try another file."]);
        }

        $stored = 'organization-logos/' . Str::uuid()->toString() . '.webp';
        self::disk()->put($stored, self::encode($source), 'public');

        return ['logo_url' => self::disk()->url($stored), 'logo_path' => $stored];
    }

    public static function delete(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, 'organization-logos/')) {
            self::disk()->delete($path);
        }
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('organizations.nominee_photos.disk'));
    }

    private static function encode(GdImage $source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw ValidationException::withMessages(['logo' => "That image couldn't be processed."]);
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, 90);

        return (string) ob_get_clean();
    }
}
