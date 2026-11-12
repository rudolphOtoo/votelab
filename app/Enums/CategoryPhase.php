<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The stage of a category's life that permissions care about. Open and Embargoed are both "Live":
 * voting is running, whether or not results are shown.
 */
enum CategoryPhase: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Closed = 'closed';
    case Revealed = 'revealed';

    public static function of(CategoryStatus $status): self
    {
        return match ($status) {
            CategoryStatus::Draft => self::Draft,
            CategoryStatus::Open, CategoryStatus::Embargoed => self::Live,
            CategoryStatus::Closed => self::Closed,
            CategoryStatus::Revealed => self::Revealed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
            self::Closed => 'Closed',
            self::Revealed => 'Revealed',
        };
    }
}
