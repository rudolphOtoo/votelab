<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Ability;
use App\Enums\CategoryAbility;
use App\Enums\CategoryPhase;
use App\Enums\NomineeAbility;
use App\Enums\OrganizationAbility;
use App\Enums\OrganizationRole;
use App\Enums\ProgramAbility;
use App\Enums\ReportingAbility;

/**
 * The single source of truth for who may do what. Deny by default: a role missing from an ability's row
 * is refused, and so is an ability missing from the table. There is no Gate::before bypass, owners
 * included. Policies combine this with data rules (valid status change, no ledger rows, …).
 *
 * A grant is either ANY (in every phase, or not tied to a category) or the list of category phases in
 * which it holds. A phase-limited grant asked about without a phase is refused (fail closed).
 */
final class PermissionMatrix
{
    public const string ANY = 'any';

    /**
     * @return array<string, array<string, self::ANY|list<CategoryPhase>>>  ability → role → grant
     */
    public static function table(): array
    {
        $all = ['owner' => self::ANY, 'admin' => self::ANY, 'staff' => self::ANY];
        $managers = ['owner' => self::ANY, 'admin' => self::ANY];
        $owner = ['owner' => self::ANY];
        $d = CategoryPhase::Draft;
        $l = CategoryPhase::Live;
        $c = CategoryPhase::Closed;
        $untilRevealed = [$d, $l, $c];

        return [
            OrganizationAbility::OrganizationView->value => $all,
            OrganizationAbility::OrganizationUpdateProfile->value => $managers,
            OrganizationAbility::OrganizationUpdateSlug->value => $owner,
            OrganizationAbility::OrganizationUpdatePayoutSettings->value => $owner,
            OrganizationAbility::MembersView->value => $all,
            OrganizationAbility::MembersManage->value => $owner,
            OrganizationAbility::MembersLeave->value => $all,

            ProgramAbility::ProgramView->value => $all,
            ProgramAbility::ProgramCreate->value => $managers,
            ProgramAbility::ProgramUpdate->value => $managers,
            ProgramAbility::ProgramPublish->value => $managers,
            ProgramAbility::ProgramArchive->value => $managers,
            ProgramAbility::ProgramDelete->value => $owner,

            CategoryAbility::CategoryView->value => $all,
            CategoryAbility::CategoryCreate->value => $managers,
            CategoryAbility::CategoryUpdateDetails->value => ['owner' => $untilRevealed, 'admin' => $untilRevealed],
            CategoryAbility::CategoryUpdatePricing->value => ['owner' => [$d], 'admin' => [$d]],
            CategoryAbility::CategoryReorder->value => $managers,
            CategoryAbility::CategoryOpen->value => $managers,
            CategoryAbility::CategoryEmbargo->value => $managers,
            CategoryAbility::CategoryUnembargo->value => $managers,
            CategoryAbility::CategoryClose->value => $managers,
            CategoryAbility::CategoryReveal->value => $managers,
            CategoryAbility::CategoryDelete->value => ['owner' => [$d], 'admin' => [$d]],

            NomineeAbility::NomineeView->value => $all,
            NomineeAbility::NomineeCreate->value => ['owner' => [$d, $l], 'admin' => [$d, $l], 'staff' => [$d]],
            NomineeAbility::NomineeUpdateProfile->value => [
                'owner' => $untilRevealed,
                'admin' => $untilRevealed,
                'staff' => [$d, $l],
            ],
            NomineeAbility::NomineeRename->value => [
                'owner' => $untilRevealed,
                'admin' => $untilRevealed,
                'staff' => [$d],
            ],
            NomineeAbility::NomineeReorder->value => ['owner' => self::ANY, 'admin' => self::ANY, 'staff' => [$d]],
            NomineeAbility::NomineeChangeCode->value => ['owner' => [$d], 'admin' => [$d], 'staff' => [$d]],
            NomineeAbility::NomineeChangeCategory->value => ['owner' => [$d], 'admin' => [$d], 'staff' => [$d]],
            NomineeAbility::NomineeArchive->value => ['owner' => $untilRevealed, 'admin' => $untilRevealed],
            NomineeAbility::NomineeDelete->value => $managers,

            ReportingAbility::ResultsView->value => $all,
            ReportingAbility::ResultsViewHidden->value => $managers,
            ReportingAbility::ResultsExport->value => $managers,
            ReportingAbility::ResultsAudit->value => $managers,
            ReportingAbility::TransactionsView->value => $managers,
            ReportingAbility::RefundsView->value => $managers,
            ReportingAbility::RefundsRecord->value => $owner,
            ReportingAbility::RevenueView->value => $managers,

            ReportingAbility::LedgerWrite->value => [],
        ];
    }

    /**
     * Every ability, area by area.
     *
     * @return list<Ability>
     */
    public static function abilities(): array
    {
        return [
            ...OrganizationAbility::cases(),
            ...ProgramAbility::cases(),
            ...CategoryAbility::cases(),
            ...NomineeAbility::cases(),
            ...ReportingAbility::cases(),
        ];
    }

    public static function allows(OrganizationRole $role, Ability $ability, ?CategoryPhase $phase = null): bool
    {
        $grant = self::table()[$ability->value][$role->value] ?? null;

        if ($grant === self::ANY) {
            return true;
        }

        return is_array($grant) && $phase !== null && in_array($phase, $grant, true);
    }

    /**
     * The table as Markdown, for the README (a test keeps the README copy identical).
     */
    public static function markdown(): string
    {
        $roles = OrganizationRole::cases();
        $lines = [
            '| Ability | '
                . implode(' | ', array_map(static fn(OrganizationRole $r): string => $r->label(), $roles))
                . ' | Notes |',
            '| :--- |' . str_repeat(' :---: |', count($roles)) . ' :--- |',
        ];

        foreach (self::abilities() as $ability) {
            $cells = array_map(static fn(OrganizationRole $role): string => self::cell($ability, $role), $roles);
            $lines[] = "| `{$ability->value}` | " . implode(' | ', $cells) . " | {$ability->description()} |";
        }

        return implode("\n", $lines);
    }

    private static function cell(Ability $ability, OrganizationRole $role): string
    {
        $grant = self::table()[$ability->value][$role->value] ?? null;

        return match (true) {
            $grant === self::ANY => '✓',
            is_array($grant) && $grant !== [] => '✓ '
                . implode(', ', array_map(static fn(CategoryPhase $p): string => $p->label(), $grant)),
            default => '✗',
        };
    }
}
