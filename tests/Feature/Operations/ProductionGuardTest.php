<?php

declare(strict_types=1);

use App\Support\ProductionGuard;
use App\Support\Site\DummySiteData;

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
        // Real public-site details (D-149); the shipped ones are dummies.
        'site.fee' => 'Ten percent of each paid vote.',
        'site.support.email' => 'help@votelab.com.gh',
        'site.support.whatsapp' => '+233 24 555 0101',
        'site.support.phone' => '+233 30 255 0101',
        'site.legal.name' => 'VoteLab Technologies Ltd',
        'site.legal.address' => '12 Liberation Road, Accra',
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

it('refuses to start while a public-site detail is still a dummy (D-149)', function (
    string $key,
    string $variable,
    string $value,
): void {
    safeProduction();
    config([$key => $value]);

    expect(ProductionGuard::problems())
        ->toBe(["{$variable} is still a dummy value: set the real one (README production checklist)."])
        ->and(ProductionGuard::enforce(...))
        ->toThrow(RuntimeException::class, $variable);
})->with(function (): Generator {
    $shipped = [
        'site.fee' => DummySiteData::FEE,
        'site.support.email' => DummySiteData::SUPPORT_EMAIL,
        'site.support.whatsapp' => DummySiteData::SUPPORT_WHATSAPP,
        'site.support.phone' => DummySiteData::SUPPORT_PHONE,
        'site.legal.name' => DummySiteData::LEGAL_NAME,
        'site.legal.address' => DummySiteData::LEGAL_ADDRESS,
    ];

    foreach (DummySiteData::SETTINGS as $key => $variable) {
        yield "{$variable}: shipped default" => [$key, $variable, $shipped[$key]];
        yield "{$variable}: bracketed placeholder" => [$key, $variable, '[to be confirmed]'];
        yield "{$variable}: example" => [$key, $variable, 'help@example.com'];
        yield "{$variable}: .invalid" => [$key, $variable, 'help@votelab.invalid'];
        yield "{$variable}: empty" => [$key, $variable, ''];
    }
});

it('lets the legal draft notice stay on in production until the legal review', function (): void {
    safeProduction();
    config(['site.legal.draft' => true]);

    expect(ProductionGuard::problems())->toBeEmpty();
});
