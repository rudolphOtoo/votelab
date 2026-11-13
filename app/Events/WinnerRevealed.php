<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Admin\RevealResult;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A category's winner(s) were revealed; the stage screen (Stage 2e) plays the reveal. After commit.
 */
final class WinnerRevealed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly RevealResult $result,
    ) {}
}
