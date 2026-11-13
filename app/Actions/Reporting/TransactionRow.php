<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

/**
 * One transaction as shown to organizers. Contact details are masked unless this row came from an exact,
 * audited lookup.
 */
final readonly class TransactionRow
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $status,
        public int $quantity,
        public int $grossAmount,
        public string $nomineeName,
        public ?string $email,
        public ?string $phone,
        public string $createdAt,
    ) {}
}
