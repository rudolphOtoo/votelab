<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A category moved between statuses (TransitionCategoryStatusAction, RevealWinnerAction). After commit.
 */
final class CategoryStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly AwardCategory $category,
        public readonly CategoryStatus $from,
        public readonly CategoryStatus $to,
        public readonly ?int $actorUserId,
    ) {}
}
