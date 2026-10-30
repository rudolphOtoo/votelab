<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VoteTransaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Paid votes were credited to the ledger. Dispatched only after the crediting transaction commits.
 */
final class VotesCredited implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly VoteTransaction $transaction,
    ) {}
}
