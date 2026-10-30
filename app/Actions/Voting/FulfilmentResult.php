<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Models\VoteTransaction;

final readonly class FulfilmentResult
{
    public function __construct(
        public ?VoteTransaction $transaction, // Null when the reference is not ours
        public bool $newlyCredited = false, // True only for the one call that credited the votes
    ) {}
}
