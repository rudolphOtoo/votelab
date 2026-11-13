<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * For seeders that create users with a known password (D-143). They refuse to run in production, even with
 * `db:seed --force`: `--force` only skips Laravel's confirmation prompt, and nothing should override this guard.
 */
trait NeverInProduction
{
    protected function refuseInProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                static::class
                . ' never runs in production, even with --force: it creates demo users whose password is "password". '
                . 'Create real organizations with `php artisan organizations:create`.',
            );
        }
    }
}
