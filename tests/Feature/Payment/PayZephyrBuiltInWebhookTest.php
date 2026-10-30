<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Events\VotesCredited;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use KenDeNigerian\PayZephyr\Events\PaymentInitiated;
use KenDeNigerian\PayZephyr\Events\PaymentVerificationFailed;
use KenDeNigerian\PayZephyr\Events\PaymentVerificationSuccess;
use KenDeNigerian\PayZephyr\Events\RefundCompleted;
use KenDeNigerian\PayZephyr\Events\RefundCreated;
use KenDeNigerian\PayZephyr\Events\RefundFailed;
use KenDeNigerian\PayZephyr\Events\SubscriptionCancelled;
use KenDeNigerian\PayZephyr\Events\SubscriptionCreated;
use KenDeNigerian\PayZephyr\Events\SubscriptionPaymentFailed;
use KenDeNigerian\PayZephyr\Events\SubscriptionRenewed;
use KenDeNigerian\PayZephyr\Events\WebhookReceived;

const PAYZEPHYR_EVENTS = [
    PaymentInitiated::class,
    PaymentVerificationSuccess::class,
    PaymentVerificationFailed::class,
    WebhookReceived::class,
    RefundCreated::class,
    RefundCompleted::class,
    RefundFailed::class,
    SubscriptionCreated::class,
    SubscriptionRenewed::class,
    SubscriptionCancelled::class,
    SubscriptionPaymentFailed::class,
];

it('turns away correctly signed webhooks at PayZephyr\'s built-in route without touching any data', function (string $provider): void {
    Queue::fake();
    Event::fake([...PAYZEPHYR_EVENTS, VotesCredited::class]);
    $transaction = VoteTransaction::factory()->create(['quantity' => 9]);
    $before = [
        'transactions' => VoteTransaction::query()->get()->toArray(),
        'nominees' => Nominee::query()->get()->toArray(),
    ];

    $body = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $transaction->payment_reference,
            'status' => 'success',
            'amount' => $transaction->gross_amount,
            'currency' => 'GHS',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        "/payments/webhook/{$provider}",
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac(
                'sha512',
                $body,
                (string) config('payments.providers.paystack.secret_key'),
            ),
        ],
        content: $body,
    )->assertNotFound();

    Queue::assertNothingPushed();
    foreach ([...PAYZEPHYR_EVENTS, VotesCredited::class] as $event) {
        Event::assertNotDispatched($event);
    }
    expect(VoteTransaction::query()->get()->toArray())
        ->toBe($before['transactions'])
        ->and(Nominee::query()->get()->toArray())
        ->toBe($before['nominees'])
        ->and(VoteRecord::query()->count())
        ->toBe(0)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::Pending);
})->with(['paystack', 'stripe']);

it('registers no listener for any PayZephyr event', function (string $event): void {
    expect(Event::hasListeners($event))->toBeFalse();
})->with(PAYZEPHYR_EVENTS);
