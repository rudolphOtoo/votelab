<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateNomineePhotoVariantsJob;
use App\Models\Nominee;
use Illuminate\Console\Command;

/**
 * Backfills srcset variants for portraits uploaded before they existed (D-127). Queues one job per chunk;
 * safe to run again (only nominees still without variants are queued, and the job re-checks). Portraits with
 * no private original (set before originals were kept) can't be re-rendered and are only counted.
 */
final class GenerateNomineePhotoVariantsCommand extends Command
{
    protected $signature = 'nominees:photo-variants
        {--dry-run : Count what would be queued without queuing anything}
        {--chunk=20 : Nominees per queued job (keep each job well under its 75 s timeout)}';

    protected $description = 'Queue generation of the 400 and 240 px portrait variants for existing nominee photos';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $pending = Nominee::query()
            ->whereNotNull('avatar_url')
            ->whereNull('avatar_variants')
            ->whereNotNull('photo_original_path');
        $withoutOriginal = Nominee::query()
            ->whereNotNull('avatar_url')
            ->whereNull('avatar_variants')
            ->whereNull('photo_original_path')
            ->count();
        $count = (clone $pending)->count();

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Would queue %d nominee(s) in %d job(s). %d portrait(s) have no original and stay without variants.',
                $count,
                (int) ceil($count / $chunk),
                $withoutOriginal,
            ));

            return self::SUCCESS;
        }

        $jobs = 0;
        $pending->select('id')->chunkById($chunk, static function ($nominees) use (&$jobs): void {
            GenerateNomineePhotoVariantsJob::dispatch(array_values(array_map(
                intval(...),
                $nominees->pluck('id')->all(),
            )));
            $jobs++;
        });

        $this->info(
            "Queued {$count} nominee(s) in {$jobs} job(s). {$withoutOriginal} portrait(s) have no original and stay without variants.",
        );

        return self::SUCCESS;
    }
}
