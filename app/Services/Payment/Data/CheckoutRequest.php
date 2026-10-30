<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

final readonly class CheckoutRequest
{
    /**
     * @param  array<string, int|string>  $metadata  Non-personal identifiers only.
     */
    public function __construct(
        public string $reference,
        public int $amount, // Pesewas
        public string $currency,
        public string $email,
        public string $callbackUrl,
        public string $subaccountCode, // Receives 100% of the payment (L-5)
        public array $metadata = [],
    ) {}
}
