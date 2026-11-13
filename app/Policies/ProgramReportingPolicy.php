<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportingAbility;
use App\Models\AwardProgram;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Program-wide results, money and ledger-reading abilities, registered as Gate abilities in
 * AppServiceProvider (e.g. Gate::allows('exportResults', $program)). Kept apart from AwardProgramPolicy:
 * seeing money is a separate decision from editing a program.
 */
final class ProgramReportingPolicy
{
    use ChecksPermissionMatrix;

    public const array ABILITIES = [
        'exportResults',
        'auditResults',
        'viewTransactions',
        'viewRefunds',
        'recordRefunds',
        'viewRevenue',
    ];

    public function exportResults(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::ResultsExport);
    }

    public function auditResults(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::ResultsAudit);
    }

    public function viewTransactions(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::TransactionsView);
    }

    public function viewRefunds(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::RefundsView);
    }

    public function recordRefunds(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::RefundsRecord);
    }

    public function viewRevenue(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ReportingAbility::RevenueView);
    }
}
