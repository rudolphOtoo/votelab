<?php

declare(strict_types=1);

use App\Support\ProductionGuard;

/**
 * A configuration that is safe for production.
 */
function safeProduction(): void
{
    app()->detectEnvironment(static fn(): string => 'production');
    // As on a production server: no pcov or xdebug, even when the machine running the tests has them (D-146).
    ProductionGuard::$extensionLoaded = static fn(string $extension): bool => false;
    config([
        'cache.default' => 'database',
        'app.debug' => false,
        'payments.providers.paystack.secret_key' => 'sk_live_real',
        'voting.phone_hash_key' => 'a-real-key',
        'voting.fingerprint_key' => 'a-real-fingerprint-key',
    ]);
}

afterEach(fn() => ProductionGuard::$extensionLoaded = null);

it('accepts a safe production configuration', function (): void {
    safeProduction();

    expect(ProductionGuard::problems())->toBeEmpty();
    ProductionGuard::enforce();
});

it('refuses each unsafe setting', function (array $override, string $problem): void {
    safeProduction();
    config($override);

    expect(implode("\n", ProductionGuard::problems()))
        ->toContain($problem)
        ->and(ProductionGuard::enforce(...))
        ->toThrow(RuntimeException::class, 'refuses to start in production');
})->with([
    'array cache' => [['cache.default' => 'array'], "can't hold atomic locks"],
    'file cache' => [['cache.default' => 'file'], "can't hold atomic locks"],
    'debug on' => [['app.debug' => true], 'APP_DEBUG'],
    'no Paystack secret' => [['payments.providers.paystack.secret_key' => ''], 'PAYSTACK_SECRET_KEY is missing'],
    'test Paystack key' => [['payments.providers.paystack.secret_key' => 'sk_test_x'], 'live Paystack keys'],
    'no phone hash key' => [['voting.phone_hash_key' => null], 'VOTING_PHONE_HASH_KEY'],
    'no fingerprint key' => [['voting.fingerprint_key' => ''], 'VOTING_FINGERPRINT_KEY is missing'],
    'PayZephyr transaction log on' => [['payments.logging.enabled' => true], 'PAYMENTS_LOGGING_ENABLED must be false'],
]);

it('lists every problem at once', function (): void {
    safeProduction();
    config(['app.debug' => true, 'voting.phone_hash_key' => '']);

    expect(ProductionGuard::problems())->toHaveCount(2);
});

it('runs at boot in production', function (): void {
    safeProduction();
    config(['app.debug' => true]);

    expect(fn() => new App\Providers\AppServiceProvider(app())->boot())->toThrow(RuntimeException::class, 'APP_DEBUG');
});

it('refuses to start with a coverage or debugging extension loaded', function (string $extension): void {
    safeProduction();
    ProductionGuard::$extensionLoaded = static fn(string $name): bool => $name === $extension;

    expect(ProductionGuard::problems())->toBe([
        "The PHP extension \"{$extension}\" is loaded: it's for tests and debugging only. Remove it from this server's PHP.",
    ]);
})->with(ProductionGuard::FORBIDDEN_EXTENSIONS);

it('checks the real PHP extensions when nothing replaces the check', function (): void {
    safeProduction();
    ProductionGuard::$extensionLoaded = null;

    expect(
        collect(ProductionGuard::problems())
            ->filter(static fn(string $p): bool => str_contains($p, 'pcov'))
            ->isNotEmpty(),
    )
        ->toBe(extension_loaded('pcov'));
});
