<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Data\PaymentVerification;

/**
 * The payment provider as the voting domain sees it. Amounts are always integer pesewas.
 */
interface PaymentGateway
{
    /**
     * Start a hosted checkout and return where to send the voter.
     *
     * @throws PaymentGatewayException
     */
    public function initialize(CheckoutRequest $request): CheckoutSession;

    /**
     * Ask the provider for the authoritative state of a payment.
     *
     * @throws PaymentGatewayException When the provider cannot be reached or does not know the reference.
     */
    public function verify(string $reference): PaymentVerification;

    /**
     * Check a webhook's signature against its raw, unparsed body.
     */
    public function hasValidWebhookSignature(string $rawBody, ?string $signature): bool;
}
