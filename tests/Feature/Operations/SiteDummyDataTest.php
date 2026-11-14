<?php

declare(strict_types=1);

use App\Mail\AccessRequestReceivedMail;
use App\Support\Site\DummySiteData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/*
 * The public site's dummy details (D-148) can't reach a real inbox: the shipped support email is on .invalid
 * (RFC 2606), and request-access notifications go exactly to the configured address (D-149).
 */

it('ships a support email that can never receive mail, in config and in .env.example', function (): void {
    preg_match_all(
        '/^#?\\s*SITE_SUPPORT_EMAIL=(\\S+)$/m',
        (string) file_get_contents(base_path('.env.example')),
        $lines,
    );

    expect(DummySiteData::SUPPORT_EMAIL)
        ->toEndWith('.invalid')
        ->and(config('site.support.email'))
        ->toBe(DummySiteData::SUPPORT_EMAIL)
        ->and($lines[1])
        ->not->toBeEmpty()
        ->each->toEndWith('.invalid');
});

it('sends the request-access notification to the configured support address only', function (): void {
    Mail::fake();
    config(['site.support.email' => 'help@votelab.com.gh']);

    $this->post('/organizers/request-access', [
        'started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        'website' => '',
        'name' => 'Efua Asante',
        'organization' => 'Takoradi Music Awards',
        'email' => 'efua@example.com',
        'phone' => '+233 24 123 4567',
        'event_type' => 'music',
    ])->assertSessionHasNoErrors();

    Mail::assertQueued(
        AccessRequestReceivedMail::class,
        static fn(AccessRequestReceivedMail $mail): bool => (
            $mail->hasTo('help@votelab.com.gh')
            && !$mail->hasTo(DummySiteData::SUPPORT_EMAIL)
            && count($mail->to) === 1
        ),
    );
});
