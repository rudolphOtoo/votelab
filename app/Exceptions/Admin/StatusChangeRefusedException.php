<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use App\Enums\CategoryStatus;
use RuntimeException;

/**
 * A category status change that isn't allowed from the category's current (locked) status.
 */
final class StatusChangeRefusedException extends RuntimeException
{
    public static function notAllowed(CategoryStatus $from, CategoryStatus $to): self
    {
        return new self("A category can't move from {$from->label()} to {$to->label()}.");
    }

    public static function useReveal(): self
    {
        return new self('Reveal winners with RevealWinnerAction, which checks pending payments and recounts votes.');
    }
}
