<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduled(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(static fn(Event $event): bool => str_contains((string) $event->command, $command));
}

it('prunes failed jobs older than 7 days every day, so failed emails do not keep one-time links', function (): void {
    $event = scheduled('queue:prune-failed');

    expect($event)
        ->not
        ->toBeNull()
        ->and((string) $event?->command)
        ->toContain('--hours=168')
        ->and($event?->expression)
        ->toBe('0 0 * * *');
});

it('still sweeps pending payments every five minutes', function (): void {
    expect(scheduled('payments:reconcile-pending')?->expression)->toBe('*/5 * * * *');
});
