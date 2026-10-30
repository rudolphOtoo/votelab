<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Voting\ReconcilePendingPaymentsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile-pending')]
#[Description('Re-verify stale pending payments with Paystack, credit late successes and expire unpaid checkouts')]
final class ReconcilePendingPaymentsCommand extends Command
{
    public function handle(ReconcilePendingPaymentsAction $reconcile): int
    {
        $summary = $reconcile->execute();

        $this->table(['Checked', 'Credited', 'Settled without credit', 'Expired', 'Still pending', 'Errors'], [[
            $summary->checked,
            $summary->credited,
            $summary->settledWithoutCredit,
            $summary->expired,
            $summary->stillPending,
            $summary->errors,
        ]]);

        return self::SUCCESS;
    }
}
