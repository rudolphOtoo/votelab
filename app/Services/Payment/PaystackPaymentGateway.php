<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Payment\Data\PaymentVerification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use KenDeNigerian\PayZephyr\Exceptions\PaymentException;
use KenDeNigerian\PayZephyr\PaymentManager;
use LogicException;

/**
 * Paystack Hosted Checkout. Verification and webhook signatures go through PayZephyr, pinned to
 * Paystack with no fallback; checkout initialization calls Paystack directly (see initialize()).
 *
 * PayZephyr reports verified amounts as floats in cedis. They are converted back to integer
 * pesewas here, at the boundary; the domain only ever sees pesewas.
 */
final readonly class PaystackPaymentGateway implements PaymentGateway
{
    private const string PROVIDER = 'paystack';

    public function __construct(
        private PaymentManager $payments,
    ) {}

    /**
     * Starts Hosted Checkout with Paystack's API directly: PayZephyr's Paystack driver cannot send
     * `subaccount`, `transaction_charge` or `bearer` (D-38). The amount is already in pesewas.
     */
    public function initialize(CheckoutRequest $request): CheckoutSession
    {
        try {
            $response = Http::baseUrl((string) config('payments.providers.paystack.base_url'))
                ->withToken((string) config('payments.providers.paystack.secret_key'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post('/transaction/initialize', [
                    'email' => $request->email,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'reference' => $request->reference,
                    'callback_url' => $request->callbackUrl,
                    'metadata' => $request->metadata,
                    // Settlement (L-4, L-5): everything to the organizer's subaccount, nothing to the
                    // main account, and the subaccount bears Paystack's fee out of the voter's processing fee.
                    'subaccount' => $request->subaccountCode,
                    'transaction_charge' => 0,
                    'bearer' => self::bearer(),
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Paystack could not be reached to start checkout.', previous: $e);
        }

        $authorizationUrl = $response->json('data.authorization_url');

        if (!$response->successful() || $response->json('status') !== true || !is_string($authorizationUrl)) {
            throw new PaymentGatewayException(sprintf(
                'Paystack refused to start checkout (HTTP %d): %s',
                $response->status(),
                is_string($response->json('message')) ? $response->json('message') : 'no message',
            ));
        }

        $reference = $response->json('data.reference', $request->reference);

        if ($reference !== $request->reference) {
            throw new PaymentGatewayException('Paystack returned a different reference than the one requested.');
        }

        return new CheckoutSession(reference: $request->reference, authorizationUrl: $authorizationUrl);
    }

    public function verify(string $reference): PaymentVerification
    {
        try {
            $result = $this->payments->verify($reference, self::PROVIDER);
        } catch (PaymentException $e) {
            throw new PaymentGatewayException('Paystack verification failed.', previous: $e);
        }

        $status = match (true) {
            $result->status === 'success' => GatewayPaymentStatus::Success,
            $result->isFailed() => GatewayPaymentStatus::Failed,
            default => GatewayPaymentStatus::Pending,
        };

        return new PaymentVerification(
            reference: $result->reference,
            status: $status,
            amount: (int) round($result->amount * 100),
            currency: strtoupper($result->currency),
            providerStatus: $result->status,
            channel: $result->channel,
            paidAt: $result->paidAt,
        );
    }

    public function hasValidWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        try {
            return $this->payments->driver(self::PROVIDER)->validateWebhook(['x-paystack-signature' => [
                $signature,
            ]], $rawBody);
        } catch (PaymentException) {
            return false;
        }
    }

    private static function bearer(): string
    {
        $bearer = config('voting.settlement.bearer');

        if (!in_array($bearer, ['account', 'subaccount'], true)) {
            throw new LogicException('voting.settlement.bearer must be "account" or "subaccount".');
        }

        return $bearer;
    }
}
