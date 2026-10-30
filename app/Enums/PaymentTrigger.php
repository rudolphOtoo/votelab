<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What caused a payment to be verified and credited.
 */
enum PaymentTrigger: string
{
    case Callback = 'callback'; // The voter returned from Paystack Checkout
    case Webhook = 'webhook'; // Paystack notified us server-to-server
    case Sweeper = 'sweeper'; // payments:reconcile-pending re-checked a stale pending payment
}
