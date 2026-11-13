<?php

declare(strict_types=1);

namespace App\Exceptions\Organizations;

use RuntimeException;

/**
 * Refused because the change would leave an organization without an owner.
 */
final class LastOwnerException extends RuntimeException
{
    public static function make(): self
    {
        return new self('An organization must always have an owner. Make someone else an owner first.');
    }
}
