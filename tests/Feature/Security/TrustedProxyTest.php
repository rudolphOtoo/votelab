<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_test/client', static fn(Request $request): array => [
        'ip' => $request->ip(),
        'secure' => $request->secure(),
        'host' => $request->getHost(),
    ]);
});

/**
 * @param  array<string, string>  $headers
 */
function clientSeenFrom(string $peer, array $headers): Illuminate\Testing\TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $peer])->getJson('/_test/client', $headers);
}

/**
 * @return mixed The value config/trustedproxy.php produces for this TRUSTED_PROXIES value.
 */
function trustedProxiesFor(string $value): mixed
{
    $_SERVER['TRUSTED_PROXIES'] = $value;

    try {
        return (require config_path('trustedproxy.php'))['proxies'];
    } finally {
        unset($_SERVER['TRUSTED_PROXIES']);
    }
}

it('parses TRUSTED_PROXIES into nobody, everybody or an explicit list', function (): void {
    expect(trustedProxiesFor(''))
        ->toBeNull()
        ->and(trustedProxiesFor('  '))
        ->toBeNull()
        ->and(trustedProxiesFor('*'))
        ->toBe('*')
        ->and(trustedProxiesFor('10.0.0.5, 173.245.48.0/20,,'))
        ->toBe(['10.0.0.5', '173.245.48.0/20']);
});

it('ignores forwarded headers when no proxy is trusted', function (): void {
    config(['trustedproxy.proxies' => null]);

    clientSeenFrom('10.0.0.5', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])->assertExactJson([
        'ip' => '10.0.0.5',
        'secure' => false,
        'host' => 'localhost',
    ]);
});

it('ignores forwarded headers from a peer that is not a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => ['10.0.0.5']]);

    clientSeenFrom('198.51.100.7', ['X-Forwarded-For' => '203.0.113.9'])->assertJsonPath('ip', '198.51.100.7');
});

it('takes the client IP and scheme from a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => ['10.0.0.0/16']]);

    clientSeenFrom('10.0.3.4', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->assertJsonPath('ip', '203.0.113.9')
        ->assertJsonPath('secure', true);
});

it('skips a client-forged entry prepended in front of the real address', function (): void {
    config(['trustedproxy.proxies' => ['173.245.48.0/20']]);

    // The voter sent "X-Forwarded-For: 6.6.6.6"; the proxy appended the address it actually saw.
    clientSeenFrom('173.245.48.10', ['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'])->assertJsonPath('ip', '203.0.113.9');
});

it('trusts whichever peer calls when set to "*"', function (): void {
    config(['trustedproxy.proxies' => '*']);

    clientSeenFrom('192.0.2.44', ['X-Forwarded-For' => '203.0.113.9'])->assertJsonPath('ip', '203.0.113.9');
});

it('never takes the host from X-Forwarded-Host, even from a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '*']);

    clientSeenFrom('10.0.0.5', ['X-Forwarded-Host' => 'evil.example'])->assertJsonPath('host', 'localhost');
});
