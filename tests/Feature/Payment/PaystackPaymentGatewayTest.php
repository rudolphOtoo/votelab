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
        metadata: ['vote_transaction_id' => 7],
    );
}

it('starts a Paystack checkout in pesewas and GHS with our reference', function (): void {
    $history = fakePaystack([
        new Response(200, [], json_encode([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'VL_TESTREF123',
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $session = app(PaystackPaymentGateway::class)->initialize(checkoutRequest(2_550));

    expect($session->authorizationUrl)
        ->toBe('https://checkout.paystack.com/abc123')
        ->and($session->reference)
        ->toBe('VL_TESTREF123')
        ->and($history)
        ->toHaveCount(1);

    $sent = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);

    expect((string) $history[0]['request']->getUri())
        ->toEndWith('/transaction/initialize')
        ->and($sent['amount'])
        ->toBe(2_550)
        ->and($sent['currency'])
        ->toBe('GHS')
        ->and($sent['reference'])
        ->toBe('VL_TESTREF123')
        ->and($sent['metadata'])
        ->toBe(['vote_transaction_id' => 7]);
});

it('converts odd pesewa amounts exactly', function (int $pesewas): void {
    $history = fakePaystack([
        new Response(200, [], json_encode([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'access_code' => 'x'],
        ], JSON_THROW_ON_ERROR)),
    ]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest($pesewas));

    $sent = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
    expect($sent['amount'])->toBe($pesewas);
})->with([1, 29, 57, 1_015, 2_549, 99_999, 4_294_967_295]);

it('wraps a refused checkout in a gateway exception', function (): void {
    fakePaystack([new Response(400, [], json_encode([
        'status' => false,
        'message' => 'Invalid key',
    ], JSON_THROW_ON_ERROR))]);

    app(PaystackPaymentGateway::class)->initialize(checkoutRequest());
})->throws(PaymentGatewayException::class);

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
