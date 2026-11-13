<?php

declare(strict_types=1);

use App\Models\AwardProgram;
use App\Models\Organization;
use App\Services\Security\VoterIdentity;
use App\Support\Throttle;

/**
 * D-105: hundreds of voters share one IP behind carrier-grade NAT or venue Wi-Fi. Voter-facing limits key on
 * the device cookie; the per-IP limits are a much higher backstop.
 */
function deviceBehindVenueIp(int $device): VoterIdentity
{
    return new VoterIdentity(
        voterKey: str_repeat('v', 64),
        ipHash: str_repeat('i', 64),
        deviceKey: hash('sha256', "device-{$device}"),
    );
}

it('lets 300 phones behind one venue IP each start a checkout and cast a free vote', function (): void {
    foreach (range(1, 300) as $device) {
        expect(Throttle::attempt('vote-checkout', deviceBehindVenueIp($device), 1))
            ->toBeNull()
            ->and(Throttle::attempt('free-vote', deviceBehindVenueIp($device)))
            ->toBeNull();
    }
});

it('still limits a single device', function (): void {
    foreach (range(1, 5) as $_) {
        expect(Throttle::attempt('vote-checkout', deviceBehindVenueIp(1), 1))->toBeNull();
    }

    expect(Throttle::attempt('vote-checkout', deviceBehindVenueIp(1), 1))->toBeInt();
    expect(Throttle::attempt('vote-checkout', deviceBehindVenueIp(2), 1))->toBeNull(); // Another phone on the same IP.
});

it('keeps a per-IP backstop', function (): void {
    config(['voting.rate_limits.free_vote_per_ip' => 3]);

    foreach (range(1, 3) as $device) {
        expect(Throttle::attempt('free-vote', deviceBehindVenueIp($device)))->toBeNull();
    }

    expect(Throttle::attempt('free-vote', deviceBehindVenueIp(4)))->toBeInt();
});

it('falls back to the IP + browser key before the device cookie exists', function (): void {
    $voter = new VoterIdentity(voterKey: str_repeat('a', 64), ipHash: str_repeat('b', 64));

    expect($voter->rateKey())
        ->toBe('voter:' . str_repeat('a', 64))
        ->and(deviceBehindVenueIp(1)->rateKey())
        ->toStartWith('device:');
});

it('keys public pages on the device cookie, with an IP backstop', function (): void {
    $organization = Organization::factory()->create(['slug' => 'org']);
    AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    config(['voting.rate_limits.public_pages_per_device' => 2, 'voting.rate_limits.public_pages_per_ip' => 4]);

    $this->withCookie('vl_voter', 'phone-a')->get('/org/awards')->assertOk();
    $this->withCookie('vl_voter', 'phone-a')->get('/org/awards')->assertOk();
    $this->withCookie('vl_voter', 'phone-a')->get('/org/awards')->assertTooManyRequests();

    $this->withCookie('vl_voter', 'phone-b')->get('/org/awards')->assertOk(); // Same IP, another phone.
    $this->withCookie('vl_voter', 'phone-c')->get('/org/awards')->assertOk();
    $this->withCookie('vl_voter', 'phone-d')->get('/org/awards')->assertTooManyRequests(); // IP backstop: 4 counted requests (a blocked one doesn't count).
});

it('keys the Paystack return on the device, so a venue returning at once is not blocked', function (): void {
    config(['voting.rate_limits.callback_per_device' => 1]);

    foreach (range(1, 40) as $device) {
        expect($this->withCookie('vl_voter', "phone-{$device}")->get('/payments/callback?reference=unknown')->status())
            ->not
            ->toBe(429);
    }

    $this->withCookie('vl_voter', 'phone-1')->get('/payments/callback?reference=unknown')->assertTooManyRequests();
});

it('ships the reviewed defaults', function (): void {
    expect((require config_path('voting.php'))['rate_limits'])->toMatchArray([
        'checkout_per_voter' => 5,
        'checkout_per_ip' => 600,
        'checkout_per_nominee' => 1200,
        'free_vote_per_voter' => 10,
        'free_vote_per_ip' => 1200,
        'public_pages_per_device' => 120,
        'public_pages_per_ip' => 3000,
        'callback_per_device' => 30,
        'callback_per_ip' => 3000,
        'share_images_per_ip' => 600,
        'stage_per_ip' => 120,
        'webhook_per_ip' => 1200,
    ]);
});
