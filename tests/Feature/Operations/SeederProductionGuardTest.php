<?php

declare(strict_types=1);

use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoProgramSeeder;
use Database\Seeders\LocalShowcaseSeeder;

/*
 * Seeders that create users with a known password never run in production, even with --force (D-143).
 */

function asEnvironment(string $environment): void
{
    app()->detectEnvironment(static fn(): string => $environment);
}

function nothingSeeded(): void
{
    expect(User::query()->count())
        ->toBe(0)
        ->and(Organization::query()->count())
        ->toBe(0)
        ->and(AwardProgram::query()->count())
        ->toBe(0)
        ->and(PlatformSetting::query()->count())
        ->toBe(0);
}

it('refuses every known-password seeder in production, with a clear message', function (string $seeder): void {
    asEnvironment('production');

    // Run directly: $this->seed() would stop at db:seed's production prompt before reaching the guard.
    expect(fn() => app($seeder)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'password');
    nothingSeeded();
})->with([DatabaseSeeder::class, DemoProgramSeeder::class, LocalShowcaseSeeder::class]);

it('refuses db:seed in production even with --force, before writing anything', function (): void {
    asEnvironment('production');

    expect(fn() => $this->artisan('db:seed', ['--force' => true]))
        ->toThrow(
            RuntimeException::class,
            'never runs in production, even with --force: it creates demo users whose password is "password". '
            . 'Create real organizations with `php artisan organizations:create`.',
        );
    nothingSeeded();
});

it('still seeds the demo program locally and in tests', function (string $environment): void {
    asEnvironment($environment);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'organizer@votelab.test')->exists())
        ->toBeTrue()
        ->and(AwardProgram::query()->count())
        ->toBeGreaterThan(0);
})->with(['local', 'testing']);
