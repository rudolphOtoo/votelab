<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

/**
 * The provider's authoritative answer about a payment. Amount is in pesewas.
 */
final readonly class PaymentVerification
{
    public function __construct(
        public string $reference,
        public GatewayPaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $providerStatus = null,
        public ?string $channel = null,
        public ?string $paidAt = null,
        // The payer's phone number, if Paystack gave one. Never stored: FulfillPaidVoteOrderAction keeps only
        // a keyed hash and the last three digits (D-86).
        #[\SensitiveParameter]
        public ?string $payerPhone = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === GatewayPaymentStatus::Success;
    }
}
