<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Jobs\FulfillPaidVoteOrderJob;
use App\Models\VoteTransaction;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakePaymentGateway;

/*
 * Payments keep confirming during `php artisan down` (D-143). Maintenance mode is faked per test process, never
 * with the shared storage/framework/down file, which would take down parallel test processes.
 */

beforeEach(function (): void {
    $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        public function data(): array
        {
            return ['status' => 503];
        }
    });
    $this->gateway = fakeGateway();
});

it('takes the public site down', function (): void {
    $this->get('/')->assertServiceUnavailable();
});

it('still accepts Paystack webhooks and queues the payment for the workers', function (): void {
    Queue::fake();
    $transaction = VoteTransaction::factory()->create();
    $body = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => $transaction->payment_reference, 'status' => 'success'],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/api/webhooks/paystack',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => FakePaymentGateway::sign($body),
        ],
        content: $body,
    )->assertOk();

    Queue::assertPushed(
        FulfillPaidVoteOrderJob::class,
        static fn(FulfillPaidVoteOrderJob $job): bool => $job->reference === $transaction->payment_reference,
    );
});

it('still credits the votes when the voter returns from Paystack', function (): void {
    $transaction = VoteTransaction::factory()->create(['quantity' => 4]);
    $this->gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    $response = $this->get('/payments/callback?reference=' . $transaction->payment_reference);

    expect($response->status())
        ->not
        ->toBe(503)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($transaction->nominee->fresh()?->votes_count)
        ->toBe(4);
});
