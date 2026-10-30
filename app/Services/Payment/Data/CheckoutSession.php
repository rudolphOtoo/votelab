<?php

declare(strict_types=1);

namespace App\Services\Payment\Data;

final readonly class CheckoutSession
{
    public function __construct(
        public string $reference,
        public string $authorizationUrl,
    ) {}
}
