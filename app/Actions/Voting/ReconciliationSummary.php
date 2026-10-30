<?php

declare(strict_types=1);

namespace App\Actions\Voting;

final readonly class ReconciliationSummary
{
    public function __construct(
        public int $checked = 0,
        public int $credited = 0,
        public int $settledWithoutCredit = 0, // failed, flagged or paid_uncredited
        public int $expired = 0,
        public int $stillPending = 0,
        public int $errors = 0, // Paystack unreachable or lock busy; retried next run
    ) {}
}
