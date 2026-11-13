<?php

declare(strict_types=1);

use App\Support\ProductionGuard;

/**
 * A configuration that is safe for production.
 */
function safeProduction(): void
{
    app()->detectEnvironment(static fn(): string => 'production');
    config([
        'cache.default' => 'database',
        'app.debug' => false,
        'payments.providers.paystack.secret_key' => 'sk_live_real',
        'voting.phone_hash_key' => 'a-real-key',
        'voting.fingerprint_key' => 'a-real-fingerprint-key',
    ]);
}

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
