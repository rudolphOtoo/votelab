<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn() => config(['app.health_check_token' => Illuminate\Support\Str::random(40)]));

/**
 * The detailed response a monitor with the token sees.
 */
function healthDetails(): Illuminate\Testing\TestResponse
{
    return test()
        ->withHeader(HealthController::TOKEN_HEADER, (string) config('app.health_check_token'))
        ->getJson('/health');
}

it('shows the public only the status word and HTTP code', function (): void {
    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);

    $this
        ->getJson('/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $this
        ->withHeader(HealthController::TOKEN_HEADER, 'wrong-token')
        ->getJson('/health')
        ->assertExactJson(['status' => 'ok']);
});

it('never shows details when no token is configured, even for an empty header', function (): void {
    config(['app.health_check_token' => '']);

    $this->withHeader(HealthController::TOKEN_HEADER, '')->getJson('/health')->assertJsonMissingPath('checks');
});

it('shows the checks to a monitor with the token', function (): void {
    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);

    healthDetails()
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.locks.ok', true)
        ->assertJsonPath('checks.scheduler.ok', true);
});

it('reports degraded when the scheduler stopped or jobs failed', function (): void {
    $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'degraded']);
    healthDetails()->assertJsonPath('checks.scheduler.ok', false);

    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);
    DB::table('failed_jobs')->insert([
        'uuid' => 'x',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'e',
        'failed_at' => now(),
    ]);

    healthDetails()
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.failed_jobs_24h.count', 1);
});

it('reports degraded when the queue is backing up', function (): void {
    config(['queue.default' => 'database']);
    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->subMinutes(10)->timestamp,
        'created_at' => now()->subMinutes(10)->timestamp,
    ]);

    healthDetails()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.queue.pending', 1);
});

it('reports down with 503 when the lock store fails, with or without the token', function (): void {
    Cache::shouldReceive('lock')->andThrow(new RuntimeException('redis down'));
    Cache::shouldReceive('get')->andReturn(now()->timestamp);

    $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'down']);
    healthDetails()->assertStatus(503)->assertJsonPath('checks.locks.ok', false);
});

it('compares the token in constant time', function (): void {
    expect((string) file_get_contents(app_path('Http/Controllers/HealthController.php')))
        ->toContain('hash_equals($expected, $given)');
});

it('is rate limited and holds no secrets', function (): void {
    $body = (string) healthDetails()->getContent();

    expect($body)
        ->not->toContain((string) config('app.key'))
        ->not->toContain('sk_')
        ->not->toContain((string) config('app.health_check_token'));
    foreach (range(1, 59) as $_) {
        $this->get('/health');
    }
    $this->get('/health')->assertTooManyRequests();
});

it('beats the scheduler heartbeat every minute', function (): void {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(static fn($e): bool => $e->description === 'health-heartbeat');

    expect($event?->expression)->toBe('* * * * *');
});
