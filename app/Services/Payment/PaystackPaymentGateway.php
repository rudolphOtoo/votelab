<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Payment\Data\PaymentVerification;
use InvalidArgumentException;
use KenDeNigerian\PayZephyr\DataObjects\ChargeRequestDTO;
use KenDeNigerian\PayZephyr\Exceptions\PaymentException;
use KenDeNigerian\PayZephyr\PaymentManager;
use LogicException;

/**
 * Paystack Hosted Checkout through PayZephyr, pinned to Paystack with no fallback provider.
 *
 * PayZephyr's DTOs carry amounts as floats in major units (cedis). Pesewas are converted
 * only at this boundary, and every conversion is checked to round-trip exactly.
 */
final readonly class PaystackPaymentGateway implements PaymentGateway
{
    private const string PROVIDER = 'paystack';

    public function __construct(
        private PaymentManager $payments,
    ) {}

    public function initialize(CheckoutRequest $request): CheckoutSession
    {
        try {
            $response = $this->payments->chargeWithFallback(
                new ChargeRequestDTO(
                    amount: self::toMajorUnits($request->amount),
                    currency: $request->currency,
                    email: $request->email,
                    reference: $request->reference,
                    callbackUrl: $request->callbackUrl,
                    metadata: $request->metadata,
                    idempotencyKey: $request->reference,
                ),
                [self::PROVIDER],
            );
        } catch (PaymentException|InvalidArgumentException $e) {
            throw new PaymentGatewayException('Paystack checkout could not be started.', previous: $e);
        }

        if ($response->reference !== $request->reference || $response->authorizationUrl === '') {
            throw new PaymentGatewayException('Paystack returned an unexpected checkout response.');
        }

        return new CheckoutSession(reference: $response->reference, authorizationUrl: $response->authorizationUrl);
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

    private static function toMajorUnits(int $pesewas): float
    {
        $major = $pesewas / 100;

        if ((int) round($major * 100) !== $pesewas) {
            throw new LogicException("Amount {$pesewas} pesewas does not convert exactly to major units.");
        }

        return (float) $major;
    }
}
