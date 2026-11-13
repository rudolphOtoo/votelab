<?php

declare(strict_types=1);

use App\Jobs\FulfillPaidVoteOrderJob;
use App\Support\Observability\Alert;
use App\Support\Observability\PiiScrubber;
use Illuminate\Queue\Events\JobFailed;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

it('scrubs Ghana phone numbers and email addresses from text', function (string $text, string $clean): void {
    expect(PiiScrubber::text($text))->toBe($clean);
})->with([
    ['Payer 0241234567 failed',                'Payer [phone] failed'],
    ['call +233 24 123 4567 now',              'call [phone] now'],
    ['233241234567',                           '[phone]'],
    ['024-123-4567',                           '[phone]'],
    ['email kwame.asante@gmail.com bounced',   'email [email] bounced'],
    ['reference VL_20261112_ABC and 42 votes', 'reference VL_20261112_ABC and 42 votes'],
]);

it('scrubs a whole Sentry event: message, exception, request, extra, user and breadcrumbs', function (): void {
    $event = Event::createEvent();
    $event->setMessage('Lookup for ama@example.com from 0551234987');
    $event->setExceptions([new ExceptionDataBag(new RuntimeException('x'))->setValue('Failed for +233551234987')]);
    $event->setRequest(['data' => ['email' => 'ama@example.com', 'nested' => ['phone' => '0551234987']]]);
    $event->setExtra(['note' => 'voter ama@example.com']);
    $event->setUser(
        UserDataBag::createFromUserIdentifier('7')->setEmail('owner@example.com')->setIpAddress('203.0.113.9'),
    );
    $event->setBreadcrumb([new Breadcrumb(
        Breadcrumb::LEVEL_INFO,
        Breadcrumb::TYPE_DEFAULT,
        'log',
        'sent to 0241234567',
        ['to' => 'x@example.com'],
    )]);

    $clean = PiiScrubber::beforeSend($event);
    $json = json_encode([
        $clean->getMessage(),
        $clean->getExceptions()[0]->getValue(),
        $clean->getRequest(),
        $clean->getExtra(),
        $clean->getUser()?->getEmail(),
        $clean->getUser()?->getIpAddress(),
        $clean->getBreadcrumbs()[0]->getMessage(),
        $clean->getBreadcrumbs()[0]->getMetadata(),
    ], JSON_THROW_ON_ERROR);

    expect($json)
        ->not->toContain('@example.com')
        ->not->toContain('551234987')
        ->not->toContain('241234567')
        ->not->toContain('203.0.113.9')->toContain('[email]')->toContain('[phone]');
});

it('never sends default PII and routes events through the scrubber', function (): void {
    expect(config('sentry.send_default_pii'))
        ->toBeFalse()
        ->and(config('sentry.before_send'))
        ->toBe([PiiScrubber::class, 'beforeSend'])
        ->and(config('sentry.before_breadcrumb'))
        ->toBe([PiiScrubber::class, 'beforeBreadcrumb'])
        ->and(config('sentry.breadcrumbs.sql_bindings'))
        ->toBeFalse();
});

it('raises an alert at error level on the alerts channel', function (): void {
    $alerts = captureLog('alerts');

    Alert::raise('test.alert', ['reference' => 'VL_1']);

    expect($alerts->hasErrorThatContains('test.alert'))->toBeTrue();
});

it('alerts on every failed job, naming the job only', function (): void {
    $alerts = captureLog('alerts');
    $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
    $job->allows(['resolveName' => 'App\\Mail\\OrganizationInvitationMail', 'getQueue' => 'default']);

    event(new JobFailed('database', $job, new RuntimeException('smtp down for ama@example.com')));

    $record = collect($alerts->getRecords())->firstWhere('message', 'queue.job_failed');
    expect($record?->context)->toBe([
        'job' => 'App\\Mail\\OrganizationInvitationMail',
        'queue' => 'default',
        'connection' => 'database',
        'exception' => RuntimeException::class,
    ]);
});

it('alerts when fulfilment gives up', function (): void {
    $alerts = captureLog('alerts');

    new FulfillPaidVoteOrderJob('VL_GIVEUP')->failed(new RuntimeException('Paystack unreachable'));

    $record = collect($alerts->getRecords())->firstWhere('message', 'fulfilment.gave_up');
    expect($record?->context)->toBe(['reference' => 'VL_GIVEUP', 'error_class' => RuntimeException::class]);
});
