<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('reports ok when the database, locks, queue and scheduler are healthy', function (): void {
    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);

    $this
        ->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.locks.ok', true)
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('reports degraded when the scheduler stopped or jobs failed', function (): void {
    $this
        ->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.scheduler.ok', false);

    Cache::put(HealthController::HEARTBEAT_KEY, now()->timestamp);
    DB::table('failed_jobs')->insert([
        'uuid' => 'x',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'e',
        'failed_at' => now(),
    ]);

    $this
        ->getJson('/health')
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

    $this->getJson('/health')->assertJsonPath('status', 'degraded')->assertJsonPath('checks.queue.pending', 1);
});

it('reports down with 503 when the lock store fails', function (): void {
    Cache::shouldReceive('lock')->andThrow(new RuntimeException('redis down'));
    Cache::shouldReceive('get')->andReturn(now()->timestamp);

    $this
        ->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'down')
        ->assertJsonPath('checks.locks.ok', false);
});

it('is rate limited and holds no secrets', function (): void {
    $body = (string) $this->get('/health')->getContent();

    expect($body)->not->toContain((string) config('app.key'))->not->toContain('sk_');
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
