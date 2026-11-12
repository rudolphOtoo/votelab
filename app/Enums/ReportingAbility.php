<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Results, money and the ledger. Ledger writes are granted to no role. Grants live in App\Support\PermissionMatrix.
 */
enum ReportingAbility: string implements Ability
{
    case ResultsView = 'results.view';
    case ResultsViewHidden = 'results.viewHidden';
    case ResultsExport = 'results.export';
    case ResultsAudit = 'results.audit';
    case TransactionsView = 'transactions.view';
    case RefundsView = 'refunds.view';
    case RefundsRecord = 'refunds.record';
    case RevenueView = 'revenue.view';
    case LedgerWrite = 'ledger.write';

    public function description(): string
    {
        return match ($this) {
            self::ResultsView => 'See counts in the admin area while results are public',
            self::ResultsViewHidden => 'See live counts while results are hidden (admin area only)',
            self::ResultsExport => 'Export reconciled results (no voter personal data)',
            self::ResultsAudit => 'Check tallies against vote records',
            self::TransactionsView => 'Search and view transactions',
            self::RefundsView => 'See the refund queue',
            self::RefundsRecord => 'Record a refund as done (password confirm; no money moves)',
            self::RevenueView => 'See revenue totals',
            self::LedgerWrite => 'Create, change or delete vote records or transactions',
        };
    }
}
