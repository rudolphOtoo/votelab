<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A new photo or a re-crop in a nominee edit (UpdateNomineeAction).
 */
final class NomineePhotoEdit
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>  avatar_url, photo_original_path, photo_crop, or nothing
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function apply(Nominee $nominee, array $data, \Illuminate\Contracts\Auth\Access\Gate $gate): array
    {
        $crop = is_array($data['crop'] ?? null) ? $data['crop'] : null;
        $upload = ($data['photo'] ?? null) instanceof UploadedFile ? $data['photo'] : null;

        if ($upload === null && ($crop === null || $nominee->photo_original_path === null)) {
            return [];
        }

        $gate->authorize('updateProfile', $nominee);

        return $upload !== null
            ? NomineePhoto::store($upload, $crop)
            : NomineePhoto::render($nominee->photo_original_path, $crop);
    }

    /**
     * @param  array<string, mixed>  $photo
     * @param  array<string, mixed>  $data
     */
    public static function describe(array $photo, array $data): ?string
    {
        if ($photo === []) {
            return null;
        }

        return ($data['photo'] ?? null) instanceof UploadedFile ? 'replaced' : 'recropped';
    }
}
