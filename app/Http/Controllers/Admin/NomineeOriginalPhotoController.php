<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Nominee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The private original of a nominee photo, for the crop editor. Only for organizers who may edit the photo.
 */
final class NomineeOriginalPhotoController
{
    public function __invoke(Nominee $nominee): Response
    {
        Gate::authorize('view', $nominee);
        abort_if($nominee->photo_original_path === null, 404);

        return Storage::disk((string) config('organizations.nominee_photos.original_disk'))->response(
            $nominee->photo_original_path,
            headers: ['Cache-Control' => 'private, max-age=600'],
        );
    }
}
