<?php

declare(strict_types=1);

use App\Console\Commands\SentryCheckCommand;
use App\Support\Observability\PiiScrubber;

it('builds a test event whose fake phone numbers and email are all scrubbed before sending', function (): void {
    $sent = PiiScrubber::beforeSend(SentryCheckCommand::testEvent());
    $json = json_encode([$sent->getMessage(), $sent->getExtra(), $sent->getTags()], JSON_THROW_ON_ERROR);

    expect($json)
        ->not->toContain('555 0199')
        ->not->toContain('5550199')
        ->not->toContain('test.voter@example.com')->toContain('[phone]')->toContain('[email]');
});

it('the raw test event really contains the fake values (so the check is meaningful)', function (): void {
    $raw = SentryCheckCommand::testEvent();

    expect((string) $raw->getMessage())
        ->toContain(SentryCheckCommand::FAKE_PHONE_LOCAL)
        ->toContain(SentryCheckCommand::FAKE_EMAIL);
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(static fn(): string => 'production');

    $this->artisan('ops:sentry-check')->expectsOutputToContain('does not run in production')->assertFailed();
});

it('says so when no DSN is configured, and sends nothing', function (): void {
    config(['sentry.dsn' => null]);

    $this->artisan('ops:sentry-check')->expectsOutputToContain('SENTRY_LARAVEL_DSN is not set')->assertFailed();
});
