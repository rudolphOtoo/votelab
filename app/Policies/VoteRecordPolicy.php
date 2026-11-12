<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportingAbility;
use App\Models\User;
use App\Models\VoteRecord;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Vote records are the ledger: read through results and audit abilities, written only by the voting
 * system, changed by no role.
 */
final class VoteRecordPolicy
{
    use ChecksPermissionMatrix;

    public function create(User $user): bool
    {
        return false; // ReportingAbility::LedgerWrite is granted to no role.
    }

    public function update(User $user, VoteRecord $record): bool
    {
        return $this->granted($user, $record->organization_id, ReportingAbility::LedgerWrite);
    }

    public function delete(User $user, VoteRecord $record): bool
    {
        return $this->granted($user, $record->organization_id, ReportingAbility::LedgerWrite);
    }
}
