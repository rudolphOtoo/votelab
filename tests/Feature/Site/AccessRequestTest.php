<?php

declare(strict_types=1);

use App\Http\Controllers\Site\AccessRequestController;
use App\Mail\AccessRequestReceivedMail;
use App\Models\AccessRequest;
use App\Support\Queues;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/*
 * Organizer "Request access" (plan/SITE.md decisions 2 and 8): stored, emailed (queued) to the support address,
 * confirmed on screen; a honeypot, a minimum time to fill the form and limits per device and network keep bots out;
 * submissions are deleted after 180 days.
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accessForm(array $overrides = [], int $secondsAgo = 30): array
{
    return [
        'started' => Crypt::encryptString((string) now()->subSeconds($secondsAgo)->getTimestamp()),
        'website' => '',
        'name' => 'Efua Asante',
        'organization' => 'Takoradi Music Awards',
        'email' => 'Efua@Example.com',
        'phone' => '+233 24 123 4567',
        'event_type' => 'music',
        'expected_date' => now()->addMonth()->toDateString(),
        'message' => 'About 12 categories, paid voting.',
        ...$overrides,
    ];
}

// A closure, not Mail::fake(...): Pest binds beforeEach callbacks to the test case.
// @mago-expect lint:prefer-first-class-callable
beforeEach(fn() => Mail::fake());

it('stores a request, emails the support address (queued) and confirms on screen', function (): void {
    $this->post('/organizers/request-access', accessForm())->assertRedirect(route('site.request-access'));
    $this->followingRedirects()->get(route('site.request-access'))->assertOk();

    $request = AccessRequest::query()->sole();
    expect($request->only(['name', 'organization', 'email', 'phone', 'event_type']))->toBe([
        'name' => 'Efua Asante',
        'organization' => 'Takoradi Music Awards',
        'email' => 'efua@example.com',
        'phone' => '+233 24 123 4567',
        'event_type' => 'music',
    ]);
    Mail::assertQueued(
        AccessRequestReceivedMail::class,
        static fn(AccessRequestReceivedMail $mail): bool => (
            $mail->hasTo(config('site.support.email'))
            && $mail->queue === Queues::MAIL
            && $mail->accessRequest->is($request)
        ),
    );
});

it('shows the confirmation after sending', function (): void {
    $this->post('/organizers/request-access', accessForm());

    $this
        ->get('/organizers/request-access')
        ->assertOk()
        ->assertSeeText('Thank you, we\'ve got your request.')
        ->assertDontSee('Send request');
});

it('explains what is missing or wrong', function (array $fields, string $error): void {
    $this
        ->from('/organizers/request-access')
        ->post('/organizers/request-access', accessForm($fields))
        ->assertRedirect('/organizers/request-access')
        ->assertSessionHasErrors($error);

    expect(AccessRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'phone with letters' => [['phone' => 'call me'], 'phone'],
    'unknown event type' => [['event_type' => 'wedding'], 'event_type'],
    'date in the past' => [['expected_date' => now()->subDay()->toDateString()], 'expected_date'],
    'message too long' => [['message' => str_repeat('a', 2001)], 'message'],
]);

it('words every error as an instruction, in the form\'s own terms', function (): void {
    $this
        ->from('/organizers/request-access')
        ->post('/organizers/request-access', accessForm([
            'name' => '',
            'organization' => '',
            'email' => '',
            'phone' => '',
            'event_type' => '',
        ]))
        ->assertSessionHasErrors([
            'name' => 'Enter your name.',
            'organization' => 'Enter the organization or event name.',
            'email' => 'Enter your email address.',
            'phone' => 'Enter a phone or WhatsApp number.',
            'event_type' => 'Choose the type of event.',
        ]);
});

it('quietly drops bots: the honeypot, a form sent too fast, or a forged start time', function (
    array $fields,
    int $secondsAgo,
): void {
    $this->post('/organizers/request-access', accessForm($fields, $secondsAgo))->assertRedirect(route(
        'site.request-access',
    ));

    expect(AccessRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'honeypot filled' => [['website' => 'https://spam.example'], 30],
    'sent too fast' => [[], AccessRequestController::MINIMUM_SECONDS - 2],
    'forged start time' => [['started' => 'not-encrypted'], 30],
]);

it('limits requests per device', function (): void {
    foreach (range(1, 3) as $attempt) {
        $this->post('/organizers/request-access', accessForm([
            'email' => "person{$attempt}@example.com",
        ]))->assertSessionHasNoErrors();
    }

    $this->post('/organizers/request-access', accessForm())->assertSessionHasErrors('form');
    expect(AccessRequest::query()->count())->toBe(3);
});

it('deletes requests 180 days after they arrive', function (): void {
    Carbon::setTestNow('2026-01-01 09:00:00');
    AccessRequest::query()->create(array_diff_key(accessForm(['name' => 'Old Request']), [
        'started' => 1,
        'website' => 1,
    ]));
    Carbon::setTestNow('2026-06-29 09:00:00'); // 179 days later
    AccessRequest::query()->create(array_diff_key(accessForm(['name' => 'Recent Request', 'expected_date' => null]), [
        'started' => 1,
        'website' => 1,
    ]));

    $this->artisan('model:prune', ['--model' => [AccessRequest::class]])->assertSuccessful();
    expect(AccessRequest::query()->pluck('name')->all())->toBe(['Old Request', 'Recent Request']);

    Carbon::setTestNow('2026-06-30 09:00:00'); // 180 days after the first
    $this->artisan('model:prune', ['--model' => [AccessRequest::class]])->assertSuccessful();
    expect(AccessRequest::query()->pluck('name')->all())->toBe(['Recent Request']);
});

it('lists recent requests for the platform owner', function (): void {
    $this->post('/organizers/request-access', accessForm());

    $this->artisan('access-requests:list')->expectsOutputToContain('Takoradi Music Awards')->assertSuccessful();
});
