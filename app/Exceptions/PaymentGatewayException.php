<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The payment provider could not complete a request. The outcome of the payment is unknown.
 */
final class PaymentGatewayException extends RuntimeException {}
