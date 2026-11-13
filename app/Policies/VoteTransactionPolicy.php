<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportingAbility;
use App\Models\User;
use App\Models\VoteTransaction;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Transactions are read-only for every role: only the voting system writes them, and corrections are
 * recorded refunds, which never change a transaction or vote record.
 */
final class VoteTransactionPolicy
{
    use ChecksPermissionMatrix;

    public function view(User $user, VoteTransaction $transaction): bool
    {
        return $this->granted($user, $transaction->organization_id, ReportingAbility::TransactionsView);
    }

    public function create(User $user): bool
    {
        return false; // ReportingAbility::LedgerWrite is granted to no role.
    }

    public function update(User $user, VoteTransaction $transaction): bool
    {
        return $this->granted($user, $transaction->organization_id, ReportingAbility::LedgerWrite);
    }

    public function delete(User $user, VoteTransaction $transaction): bool
    {
        return $this->granted($user, $transaction->organization_id, ReportingAbility::LedgerWrite);
    }
}
