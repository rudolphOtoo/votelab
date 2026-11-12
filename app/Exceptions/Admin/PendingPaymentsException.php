<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use RuntimeException;

/**
 * Reveal refused: payments started before the close are still unconfirmed and could still be credited.
 */
final class PendingPaymentsException extends RuntimeException
{
    public function __construct(
        public readonly int $pending,
    ) {
        parent::__construct(
            "{$pending} payment(s) started before voting closed are still being confirmed. Wait, or reveal anyway "
            . '(they will be refunded instead of counted).',
        );
    }
}
