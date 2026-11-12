<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\CategoryStatus;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\ShareImages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a nominee's campaign image (og, square, story). Public, rate limited with the other public pages,
 * and only for nominees a voter could see: published program, category past Draft, not archived.
 * `?download=1` sends it as an attachment (for WhatsApp Status).
 */
final class ShareImageController
{
    public function __invoke(
        Request $request,
        Organization $organization,
        AwardProgram $awardProgram,
        Nominee $nominee,
        string $format,
    ): Response {
        abort_unless(
            $awardProgram->organization_id === $organization->id
            && $awardProgram->status->isPublic()
            && !$nominee->isArchived()
            && $nominee->awardCategory()->value('status') !== CategoryStatus::Draft
            && array_key_exists($format, ShareImages::FORMATS),
            404,
        );

        $path = ShareImages::path($nominee, $format);
        $name = "{$nominee->nominee_code}-{$format}.jpg";
        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400'];

        return $request->boolean('download')
            ? ShareImages::disk()->download($path, $name, $headers)
            : ShareImages::disk()->response($path, $name, $headers);
    }
}
