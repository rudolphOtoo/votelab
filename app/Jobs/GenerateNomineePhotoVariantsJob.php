<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Nominee;
use App\Support\NomineePhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Renders the srcset variants for a chunk of nominees whose portraits predate them (D-127). Idempotent:
 * nominees that already have variants, or no private original to render from, are skipped. The save is
 * conditional on the portrait being unchanged, so a re-crop made meanwhile (which writes its own variants)
 * is never overwritten with variants of the old crop.
 */
final class GenerateNomineePhotoVariantsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  list<int>  $nomineeIds
     */
    public function __construct(
        public readonly array $nomineeIds,
    ) {}

    public function handle(): void
    {
        $nominees = Nominee::query()
            ->whereKey($this->nomineeIds)
            ->whereNull('avatar_variants')
            ->whereNotNull('photo_original_path')
            ->whereNotNull('avatar_url')
            ->get(['id', 'avatar_url', 'photo_original_path', 'photo_crop']);

        foreach ($nominees as $nominee) {
            try {
                $variants = NomineePhoto::variants((string) $nominee->photo_original_path, $nominee->photo_crop);
            } catch (ValidationException) {
                Log::warning('nominee_photo.variants_unreadable', ['nominee_id' => $nominee->id]);

                continue;
            }

            Nominee::query()
                ->whereKey($nominee->id)
                ->where('avatar_url', $nominee->avatar_url)
                ->whereNull('avatar_variants')
                ->update(['avatar_variants' => json_encode($variants, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
        }
    }
}
