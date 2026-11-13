<?php

declare(strict_types=1);

use App\Services\Security\VoteFingerprintService;
use Illuminate\Http\Request;

function voterRequest(
    string $ip = '41.66.10.20',
    string $agent = 'Mozilla/5.0 (iPhone)',
    ?string $cookie = 'device-1',
): Request {
    $request = Request::create('/', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $agent]);

    if ($cookie !== null) {
        $request->cookies->set(config('voting.voter_cookie'), $cookie);
    }

    return $request;
}

it('is deterministic for the same browser on the same network', function (): void {
    $service = app(VoteFingerprintService::class);

    expect($service->identify(voterRequest()))->toEqual($service->identify(voterRequest()));
});

it('tells apart devices, browsers and networks', function (): void {
    $service = app(VoteFingerprintService::class);
    $base = $service->identify(voterRequest())->voterKey;

    expect($service->identify(voterRequest(ip: '41.66.10.21'))->voterKey)
        ->not->toBe($base)->and($service->identify(voterRequest(agent: 'Mozilla/5.0 (Android)'))->voterKey)
        ->not->toBe($base)->and($service->identify(voterRequest(cookie: 'device-2'))->voterKey)
        ->not->toBe($base)->and($service->identify(voterRequest(cookie: null))->voterKey)
        ->not->toBe($base);
});

it('hashes the network separately from the device', function (): void {
    $service = app(VoteFingerprintService::class);

    expect($service->identify(voterRequest(agent: 'A', cookie: 'x'))->ipHash)
        ->toBe($service->identify(voterRequest(agent: 'B', cookie: 'y'))->ipHash);
});

it('stores no raw personal data: keyed 64-character hashes only', function (): void {
    $identity = app(VoteFingerprintService::class)->identify(voterRequest());

    expect($identity->voterKey)
        ->toMatch('/^[0-9a-f]{64}$/')
        ->and($identity->ipHash)
        ->toMatch('/^[0-9a-f]{64}$/')
        ->and($identity->ipHash)
        ->not->toBe(hash('sha256', '41.66.10.20')); // keyed, so not brute-forceable from IPs alone
});

it('changes every hash when the fingerprint key changes', function (): void {
    $before = app(VoteFingerprintService::class)->identify(voterRequest());
    config(['voting.fingerprint_key' => 'rotated-key']);
    $after = app(VoteFingerprintService::class)->identify(voterRequest());

    expect($after->voterKey)->not->toBe($before->voterKey)->and($after->ipHash)->not->toBe($before->ipHash);
});

it('never uses APP_KEY for phone hashes: a fixed test key outside production', function (): void {
    config(['voting.phone_hash_key' => null]);

    expect(App\Services\Security\PhoneHasher::key())
        ->toBe(App\Services\Security\PhoneHasher::NON_PRODUCTION_KEY)
        ->not
        ->toBe(config('app.key'))
        ->and(app(App\Services\Security\PhoneHasher::class)->hashForSearch('0241234567'))
        ->toBe(hash_hmac('sha256', '233241234567', App\Services\Security\PhoneHasher::NON_PRODUCTION_KEY));
});

it('refuses to boot in production without VOTING_PHONE_HASH_KEY', function (): void {
    // Otherwise safe for production (D-106), so the phone key is the only problem reported.
    config([
        'cache.default' => 'database',
        'app.debug' => false,
        'payments.providers.paystack.secret_key' => 'sk_live_x',
        'voting.phone_hash_key' => '',
    ]);
    app()->detectEnvironment(static fn(): string => 'production');

    expect(fn() => new App\Providers\AppServiceProvider(app())->boot())
        ->toThrow(RuntimeException::class, 'VOTING_PHONE_HASH_KEY is missing.');

    config(['voting.phone_hash_key' => 'a-real-production-key']);
    expect(App\Services\Security\PhoneHasher::key())->toBe('a-real-production-key');
});

it('never uses APP_KEY for fingerprints: a fixed test key outside production', function (): void {
    config(['voting.fingerprint_key' => null]);
    $request = voterRequest();

    expect(VoteFingerprintService::key())
        ->toBe(VoteFingerprintService::NON_PRODUCTION_KEY)
        ->not
        ->toBe(config('app.key'))
        ->and(app(VoteFingerprintService::class)->identify($request)->ipHash)
        ->toBe(hash_hmac('sha256', '41.66.10.20', VoteFingerprintService::NON_PRODUCTION_KEY));
});

it('refuses to fingerprint in production without VOTING_FINGERPRINT_KEY', function (): void {
    config(['voting.fingerprint_key' => '']);
    app()->detectEnvironment(static fn(): string => 'production');

    expect(VoteFingerprintService::key(...))->toThrow(RuntimeException::class, 'VOTING_FINGERPRINT_KEY must be set');

    config(['voting.fingerprint_key' => 'a-real-production-key']);
    expect(VoteFingerprintService::key())->toBe('a-real-production-key');
});
