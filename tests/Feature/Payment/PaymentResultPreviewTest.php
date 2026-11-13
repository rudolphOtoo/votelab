<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Models\VoteTransaction;

// /dev/payments/{status}: local preview of the payment result page (plan/PREVIEW.md). Never anywhere else.

it('does not exist outside APP_ENV=local', function (string $environment): void {
    VoteTransaction::factory()->status(TransactionStatus::Successful)->create();
    app()->detectEnvironment(static fn(): string => $environment);

    $this->get('/dev/payments/successful')->assertNotFound();
})->with(['production', 'staging', 'testing']);

it('shows the result page for the latest payment in each state, locally', function (
    TransactionStatus $status,
    string $heading,
): void {
    VoteTransaction::factory()->status($status)->create(['payment_reference' => 'VL_OLDER']);
    VoteTransaction::factory()->status($status)->create(['payment_reference' => 'VL_LATEST']);
    app()->detectEnvironment(static fn(): string => 'local');

    $this
        ->get("/dev/payments/{$status->value}")
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee($heading)
        ->assertSee('VL_LATEST')
        ->assertDontSee('VL_OLDER')
        ->assertSee('data-payment-state="' . $status->value . '"', false);
})->with([
    [TransactionStatus::Successful,     'Your votes are in'],
    [TransactionStatus::Pending,        'Confirming your payment'],
    [TransactionStatus::Failed,         'Payment not completed'],
    [TransactionStatus::PaidUncredited, 'Voting closed before your payment was confirmed'],
]);

it('is read only: never verifies with Paystack or changes the payment', function (): void {
    $gateway = fakeGateway();
    $pending = VoteTransaction::factory()->status(TransactionStatus::Pending)->create();
    app()->detectEnvironment(static fn(): string => 'local');

    $this->get('/dev/payments/pending')->assertOk();

    expect($gateway->verified)->toBeEmpty()->and($pending->refresh()->status)->toBe(TransactionStatus::Pending);
});

it('is a 404 for an unknown state or a state with no payments', function (): void {
    app()->detectEnvironment(static fn(): string => 'local');

    $this->get('/dev/payments/winning')->assertNotFound();
    $this->get('/dev/payments/refunded')->assertNotFound();
});
