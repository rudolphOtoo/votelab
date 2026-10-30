<?php

declare(strict_types=1);

use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Payment\PaystackPaymentGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KenDeNigerian\PayZephyr\Drivers\PaystackDriver;
use KenDeNigerian\PayZephyr\PaymentManager;

// @mago-expect lint:no-literal-password
const TEST_PAYSTACK_SECRET = 'sk_test_votelab_suite';

/**
 * Swap PayZephyr's Paystack driver for one whose HTTP client returns canned responses
 * (the mechanism PayZephyr documents in docs/testing.md). Returns the request history.
 *
 * @param  list<Response>  $responses
 * @return ArrayObject<int, array{request: Psr\Http\Message\RequestInterface}>
 */
function fakePaystack(array $responses): ArrayObject
{
    config([
        'payments.providers.paystack.secret_key' => TEST_PAYSTACK_SECRET,
        'payments.health_check.enabled' => false,
    ]);

    /** @var ArrayObject<int, array{request: Psr\Http\Message\RequestInterface}> $history */
    $history = new ArrayObject();
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $driver = new PaystackDriver(config('payments.providers.paystack'));
    $driver->setClient(new Client(['handler' => $stack]));

    // PaymentManager snapshots its config when built, so rebuild it after changing config.
    app()->forgetInstance('payments.config');
    app()->forgetInstance(PaymentManager::class);
    $manager = app(PaymentManager::class);
    $property = new ReflectionProperty($manager, 'drivers');
    $property->setValue($manager, ['paystack' => $driver]);

    return $history;
}

function checkoutRequest(int $amount = 2_550): CheckoutRequest
{
    return new CheckoutRequest(
        reference: 'VL_TESTREF123',
        amount: $amount,
        currency: 'GHS',
        email: 'anon-abc@voters.votelab.example',
        callbackUrl: 'https://votelab.test/payments/callback',
        subaccountCode: 'ACCT_organizer01',
        metadata: ['vote_transaction_id' => 7],
    );
}

function paystackInitialized(): array
{
    return [
        'status' => true,
        'message' => 'Authorization URL created',
        'data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123',
            'access_code' => 'abc123',
            'reference' => 'VL_TESTREF123',
        ],
    ];
}

it('starts a Paystack checkout in pesewas that settles 100% to the organizer subaccount', function (): void {
    config([
        'payments.providers.paystack.secret_key' => TEST_PAYSTACK_SECRET,
        'voting.settlement.bearer' => 'subaccount',
    ]);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(paystackInitialized())]);

    $session = app(PaystackPaymentGateway::class)->initialize(checkoutRequest(2_550));

    expect($session->authorizationUrl)
        ->toBe('https://checkout.paystack.com/abc123')
        ->and($session->reference)
        ->toBe('VL_TESTREF123');

    Http::assertSentCount(1);
    Http::assertSent(
        static fn(Request $request): bool => (
            $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer ' . TEST_PAYSTACK_SECRET)
            && $request->data() === [
                'email' => 'anon-abc@voters.votelab.example',
                'amount' => 2_550,
                'currency' => 'GHS',
                'reference' => 'VL_TESTREF123',
                'callback_url' => 'https://votelab.test/payments/callback',
                'metadata' => ['vote_transaction_id' => 7],
                'subaccount' => 'ACCT_organizer01',
                'transaction_charge' => 0,
                'bearer' => 'subaccount',
            ]
        ),
    );
});

it('sends amounts as exact integer pesewas', function (int $pesewas): void {
    Http::fake(['*' => Http::response(paystackInitialized())]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest($pesewas));

    Http::assertSent(static fn(Request $request): bool => $request['amount'] === $pesewas);
})->with([1, 29, 57, 1_015, 2_549, 99_999, 4_294_967_295]);

it('wraps a refused checkout in a gateway exception', function (array $body, int $status): void {
    Http::fake(['*' => Http::response($body, $status)]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest());
})->with([
    'bad request' => [['status' => false, 'message' => 'Invalid subaccount'], 400],
    'status false on 200' => [['status' => false, 'message' => 'Duplicate Transaction Reference'], 200],
    'no authorization url' => [['status' => true, 'data' => []], 200],
    'server error' => [[], 500],
])->throws(PaymentGatewayException::class);

it('wraps an unreachable Paystack in a gateway exception', function (): void {
    Http::fake(['*' => Http::failedConnection()]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest());
})->throws(PaymentGatewayException::class);

it('rejects a checkout answered for a different reference', function (): void {
    $body = paystackInitialized();
    $body['data']['reference'] = 'VL_OTHER';
    Http::fake(['*' => Http::response($body)]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest());
})->throws(PaymentGatewayException::class);

it('refuses a bearer setting Paystack would not understand', function (): void {
    config(['voting.settlement.bearer' => 'platform']);
    Http::fake(['*' => Http::response(paystackInitialized())]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest());
})->throws(LogicException::class);

it('reports a verified payment in pesewas', function (): void {
    fakePaystack([
        new Response(200, [], json_encode([
            'status' => true,
            'data' => [
                'reference' => 'VL_TESTREF123',
                'status' => 'success',
                'amount' => 2_550,
                'currency' => 'GHS',
                'paid_at' => '2026-10-30T20:00:00Z',
                'channel' => 'mobile_money',
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $verification = app(PaystackPaymentGateway::class)->verify('VL_TESTREF123');

    expect($verification->status)
        ->toBe(GatewayPaymentStatus::Success)
        ->and($verification->amount)
        ->toBe(2_550)
        ->and($verification->currency)
        ->toBe('GHS')
        ->and($verification->reference)
        ->toBe('VL_TESTREF123')
        ->and($verification->channel)
        ->toBe('mobile_money');
});

it('maps provider statuses without ever treating an unknown one as success', function (
    string $raw,
    GatewayPaymentStatus $expected,
): void {
    fakePaystack([
        new Response(200, [], json_encode([
            'status' => true,
            'data' => ['reference' => 'VL_TESTREF123', 'status' => $raw, 'amount' => 100, 'currency' => 'GHS'],
        ], JSON_THROW_ON_ERROR)),
    ]);

    expect(app(PaystackPaymentGateway::class)->verify('VL_TESTREF123')->status)->toBe($expected);
})->with([
    'failed' => ['failed', GatewayPaymentStatus::Failed],
    'abandoned (voter may still finish)' => ['abandoned', GatewayPaymentStatus::Pending],
    'reversed' => ['reversed', GatewayPaymentStatus::Pending],
    'ongoing' => ['ongoing', GatewayPaymentStatus::Pending],
    'unknown' => ['something_new', GatewayPaymentStatus::Pending],
]);

it('wraps an unreachable verification in a gateway exception', function (): void {
    fakePaystack([new Response(500, [], 'oops')]);

    app(PaystackPaymentGateway::class)->verify('VL_TESTREF123');
})->throws(PaymentGatewayException::class);

it('accepts only webhooks signed with the secret key over the raw body', function (): void {
    fakePaystack([]);
    $gateway = app(PaystackPaymentGateway::class);
    $body = '{"event":"charge.success","data":{"reference":"VL_TESTREF123"}}';

    expect($gateway->hasValidWebhookSignature($body, hash_hmac('sha512', $body, TEST_PAYSTACK_SECRET)))
        ->toBeTrue()
        ->and($gateway->hasValidWebhookSignature($body, hash_hmac('sha512', $body, 'wrong')))
        ->toBeFalse()
        ->and($gateway->hasValidWebhookSignature($body . ' ', hash_hmac('sha512', $body, TEST_PAYSTACK_SECRET)))
        ->toBeFalse()
        ->and($gateway->hasValidWebhookSignature($body, null))
        ->toBeFalse()
        ->and($gateway->hasValidWebhookSignature($body, ''))
        ->toBeFalse();
});
