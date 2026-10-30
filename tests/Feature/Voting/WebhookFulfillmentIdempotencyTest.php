<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\TransactionStatus;
use App\Jobs\FulfillPaidVoteOrderJob;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\PaystackPaymentGateway;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakePaymentGateway;

/**
 * POST a webhook exactly as Paystack would: raw JSON body plus its HMAC-SHA512 signature.
 *
 * @param  array<string, mixed>  $payload
 */
function deliverWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call(
        'POST',
        route('webhooks.paystack'),
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? FakePaymentGateway::sign($body),
        ],
        content: $body,
    );
}

/**
 * @return array<string, mixed>
 */
function chargeSuccess(VoteTransaction $transaction, int $amount = 999_999): array
{
    // The payload amount is deliberately wrong: it must never be trusted.
    return [
        'event' => 'charge.success',
        'data' => [
            'reference' => $transaction->payment_reference,
            'status' => 'success',
            'amount' => $amount,
            'currency' => 'GHS',
        ],
    ];
}

beforeEach(function (): void {
    $this->gateway = fakeGateway();
    $this->transaction = VoteTransaction::factory()->create(['quantity' => 12]);
    $this->gateway->reports($this->transaction->payment_reference, amount: $this->transaction->gross_amount);
});

it('credits the stored quantity once, however many times Paystack delivers the webhook', function (): void {
    deliverWebhook(chargeSuccess($this->transaction))->assertOk();
    deliverWebhook(chargeSuccess($this->transaction))->assertOk();
    deliverWebhook(chargeSuccess($this->transaction))->assertOk();

    expect($this->transaction->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($this->transaction->nominee->fresh()?->votes_count)
        ->toBe(12)
        ->and(VoteRecord::query()->count())
        ->toBe(1);
});

it('rejects a webhook with a bad signature before reading it', function (): void {
    Queue::fake();

    deliverWebhook(chargeSuccess($this->transaction), signature: str_repeat('a', 128))->assertUnauthorized();

    Queue::assertNothingPushed();
    expect($this->gateway->verified)->toBeEmpty();
});

it('rejects a webhook with no signature', function (): void {
    Queue::fake();

    test()->postJson(route('webhooks.paystack'), chargeSuccess($this->transaction))->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('acknowledges fast and leaves verification to the queue', function (): void {
    Queue::fake();

    deliverWebhook(chargeSuccess($this->transaction))->assertOk();

    Queue::assertPushed(
        FulfillPaidVoteOrderJob::class,
        fn(FulfillPaidVoteOrderJob $job): bool => $job->reference === $this->transaction->payment_reference,
    );
    expect($this->gateway->verified)->toBeEmpty();
});

it('acknowledges but ignores events other than charge.success', function (): void {
    Queue::fake();

    deliverWebhook([
        'event' => 'transfer.success',
        'data' => ['reference' => $this->transaction->payment_reference],
    ])->assertOk();
    deliverWebhook(['event' => 'charge.success', 'data' => []])->assertOk();

    Queue::assertNothingPushed();
});

it('is CSRF-exempt and stateless', function (): void {
    deliverWebhook(chargeSuccess($this->transaction))->assertOk()->assertCookieMissing(config('session.cookie'));
});

it('verifies real Paystack signatures through PayZephyr', function (): void {
    Queue::fake();
    config(['payments.providers.paystack.secret_key' => 'sk_test_votelab_suite']);
    app()->forgetInstance('payments.config');
    app()->forgetInstance(KenDeNigerian\PayZephyr\PaymentManager::class);
    app()->bind(PaymentGateway::class, PaystackPaymentGateway::class);

    $body = json_encode(chargeSuccess($this->transaction), JSON_THROW_ON_ERROR);

    deliverWebhook(chargeSuccess($this->transaction), hash_hmac('sha512', $body, 'sk_test_votelab_suite'))->assertOk();
    deliverWebhook(
        chargeSuccess($this->transaction),
        hash_hmac('sha512', $body, 'sk_test_someone_else'),
    )->assertUnauthorized();

    Queue::assertPushed(FulfillPaidVoteOrderJob::class, 1);
});

it('keeps retrying while Paystack is unreachable without crediting', function (): void {
    Queue::fake();
    $this->gateway->failVerification();

    $job = new FulfillPaidVoteOrderJob($this->transaction->payment_reference)->withFakeQueueInteractions();
    $job->handle(app(App\Actions\Voting\FulfillPaidVoteOrderAction::class));

    $job->assertReleased();
    expect($this->transaction->fresh()?->status)->toBe(TransactionStatus::Pending);
});
