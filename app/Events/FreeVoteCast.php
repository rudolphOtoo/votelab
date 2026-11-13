<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VoteRecord;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A free vote was recorded in the ledger. Dispatched only after the transaction commits.
 */
final class FreeVoteCast implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly VoteRecord $voteRecord,
    ) {}
}
