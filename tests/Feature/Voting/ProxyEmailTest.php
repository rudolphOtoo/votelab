<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Livewire\Public\VoteDrawer;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use Livewire\Livewire;

// Paystack requires an email. Without one from the voter, VoteLab sends a random address on the configured
// proxy domain (VOTING_PROXY_EMAIL_DOMAIN): nothing derived from the voter, nothing stored, different every time.

function proxyCheckout(string $email): App\Services\Payment\Data\CheckoutRequest
{
    $gateway = fakeGateway();
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create(['price_per_vote' => 100]);
    $nominee = Nominee::factory()->for($category)->create(['name' => 'Akosua Mensah']);

    Livewire::withHeaders(['User-Agent' => 'Mozilla/5.0 (Android)'])
        ->test(VoteDrawer::class, ['nomineeId' => $nominee->id])
        ->set('email', $email)
        ->call('checkout');

    return $gateway->initialized[0];
}

it('sends a random address on the proxy domain when the voter gives no email, and never stores it', function (): void {
    config(['voting.proxy_email_domain' => 'voters.votelab.example']);

    $request = proxyCheckout('  ');

    expect($request->email)
        ->toMatch('/^anon-[a-z0-9]{24}@voters\.votelab\.example$/')
        ->not->toContain('akosua')
        ->not->toContain('mensah')->and(VoteTransaction::query()->sole()->voter_email)->toBeNull();
});

it('uses a different proxy address for every checkout', function (): void {
    $first = proxyCheckout('')->email;
    $second = proxyCheckout('')->email;

    expect($first)->not->toBe($second);
});

it('uses the configured domain, so production can point it at a domain the platform controls', function (): void {
    config(['voting.proxy_email_domain' => 'no-reply-voters.votelab.app']);

    expect(proxyCheckout('')->email)->toEndWith('@no-reply-voters.votelab.app');
});

it('passes a real email through unchanged (lower-cased) and stores it', function (): void {
    $request = proxyCheckout('Ama@Example.COM');

    expect($request->email)
        ->toBe('ama@example.com')
        ->and(VoteTransaction::query()->sole()->voter_email)
        ->toBe('ama@example.com');
});
