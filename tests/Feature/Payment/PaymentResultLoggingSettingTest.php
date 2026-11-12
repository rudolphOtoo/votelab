<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use App\Services\Payment\PaystackPaymentGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Exceptions;
use KenDeNigerian\PayZephyr\Drivers\PaystackDriver;
use KenDeNigerian\PayZephyr\PaymentManager;

/*
 * D-122: a missing PAYMENTS_LOGGING_ENABLED made every pending payment page fail, because PayZephyr's default
 * turned on its transaction log, which reads a table VoteLab doesn't have. These run the real Paystack gateway
 * and PayZephyr (only Paystack's HTTP responses are canned).
 */

/**
 * PayZephyr's `logging` block exactly as config/payments.php builds it when the variable isn't set anywhere.
 *
 * @return array<string, mixed>
 */
function paymentsLoggingWithoutEnv(): array
{
    $name = 'PAYMENTS_LOGGING_ENABLED';
    $saved = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
    putenv($name);
    unset($_ENV[$name], $_SERVER[$name]);

    try {
        /** @var array{logging: array<string, mixed>} $config */
        $config = require config_path('payments.php');

        return $config['logging'];
    } finally {
        if ($saved[0] !== false) {
            putenv("{$name}={$saved[0]}");
        }

        if ($saved[1] !== null) {
            $_ENV[$name] = $saved[1];
        }

        if ($saved[2] !== null) {
            $_SERVER[$name] = $saved[2];
        }
    }
}

/**
 * The real gateway, with PayZephyr's Paystack driver answering verify with the given Paystack status.
 */
function realGatewayVerifying(VoteTransaction $transaction, string $paystackStatus): void
{
    config([
        'payments.providers.paystack.secret_key' => 'sk_test_votelab_suite',
        'payments.health_check.enabled' => false,
    ]);

    $driver = new PaystackDriver(config('payments.providers.paystack'));
    $driver->setClient(new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode([
            'status' => true,
            'data' => [
                'reference' => $transaction->payment_reference,
                'status' => $paystackStatus,
                'amount' => $transaction->gross_amount,
                'currency' => 'GHS',
                'channel' => 'card',
            ],
        ], JSON_THROW_ON_ERROR)),
    ]))]));

    // PaymentManager snapshots its config when built, so rebuild it after changing config.
    app()->forgetInstance('payments.config');
    app()->forgetInstance(PaymentManager::class);
    $manager = app(PaymentManager::class);
    new ReflectionProperty($manager, 'drivers')->setValue($manager, ['paystack' => $driver]);
    app()->instance(PaymentGateway::class, app(PaystackPaymentGateway::class));
}

function pendingPaidVote(): VoteTransaction
{
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create(['price_per_vote' => 100]);

    return VoteTransaction::factory()->for(Nominee::factory()->for($category))->create([
        'status' => TransactionStatus::Pending,
        'quantity' => 10,
        'verified_at' => null,
    ]);
}

it('defaults PayZephyr transaction logging to off when the variable is not set', function (): void {
    expect(paymentsLoggingWithoutEnv()['enabled'])->toBeFalse();
});

it('renders every payment result with the logging variable unset', function (
    string $paystackStatus,
    TransactionStatus $expected,
    string $heading,
): void {
    config(['payments.logging' => paymentsLoggingWithoutEnv()]);
    $transaction = pendingPaidVote();
    realGatewayVerifying($transaction, $paystackStatus);

    $this
        ->get('/payments/callback?reference=' . $transaction->payment_reference)
        ->assertOk()
        ->assertSee($heading)
        ->assertSee($transaction->payment_reference);

    expect($transaction->refresh()->status)->toBe($expected);
})->with([
    'success' => ['success', TransactionStatus::Successful, 'Your votes are in'],
    'pending' => ['ongoing', TransactionStatus::Pending, 'Confirming your payment'],
    'failed' => ['failed', TransactionStatus::Failed, 'Payment not completed'],
]);

it('still shows the voter their payment if PayZephyr logging is switched on without its table', function (): void {
    Exceptions::fake();
    config(['payments.logging.enabled' => true]);
    $transaction = pendingPaidVote();
    realGatewayVerifying($transaction, 'success');

    $this
        ->get('/payments/callback?reference=' . $transaction->payment_reference)
        ->assertOk()
        ->assertSee($transaction->payment_reference);

    // Not credited by this request (the webhook or reconciler settles it); the fault is reported, not shown.
    expect($transaction->refresh()->status)->toBe(TransactionStatus::Pending);
    // The underlying fault (in tests PayZephyr fails resolving its log's connection; in production, the
    // missing table) is reported for Sentry. The voter only ever sees the gateway-level outcome.
    $faults = array_filter(
        Exceptions::getFacadeRoot()->reported(),
        static fn(Throwable $e): bool => !$e instanceof PaymentGatewayException,
    );
    expect($faults)->not->toBeEmpty();
});

it('never selects PayZephyr defaults VoteLab does not use: no fallback provider, cedis', function (): void {
    expect(config('payments.fallback'))->toBeNull()->and(config('payments.currency.default'))->toBe('GHS');
});
