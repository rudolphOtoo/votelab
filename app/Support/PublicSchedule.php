<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Dates and the program's voting status line, in voter language and Africa/Accra time (UI brief §4, §7).
 * Category statuses decide whether voting is open; the program's dates only say when it opens or closes.
 */
final class PublicSchedule
{
    public const string TIMEZONE = 'Africa/Accra';

    /**
     * "Sat 14 Dec, 8:00 PM" in Accra time.
     */
    public static function format(CarbonInterface $at): string
    {
        return $at->setTimezone(self::TIMEZONE)->format('D j M, g:i A');
    }

    /**
     * The status line for a program's landing page.
     *
     * @param  Collection<int, AwardCategory>  $categories  The program's public (non-draft) categories.
     * @return array{label: string, tone: 'success'|'warning'|'neutral'|'brand'}
     */
    public static function programStatus(
        AwardProgram $program,
        Collection $categories,
        ?CarbonInterface $now = null,
    ): array {
        $now ??= now();
        $statuses = $categories->map(static fn(AwardCategory $category): CategoryStatus => $category->status);
        $closes = $program->ends_at !== null && $program->ends_at->greaterThan($now)
            ? ' — closes ' . self::format($program->ends_at)
            : '';

        return match (true) {
            $statuses->contains(static fn(CategoryStatus $s): bool => $s->isVotingAllowed()) => [
                'label' => 'Voting open' . $closes,
                'tone' => 'success',
            ],
            $statuses->isEmpty() || $program->starts_at !== null && $program->starts_at->greaterThan($now) => [
                'label' => $program->starts_at !== null && $program->starts_at->greaterThan($now)
                    ? 'Voting opens ' . self::format($program->starts_at)
                    : 'Voting opens soon',
                'tone' => 'neutral',
            ],
            $statuses->every(static fn(CategoryStatus $s): bool => $s === CategoryStatus::Revealed) => [
                'label' => 'Winners announced',
                'tone' => 'brand',
            ],
            default => ['label' => 'Voting closed', 'tone' => 'neutral'],
        };
    }
}
