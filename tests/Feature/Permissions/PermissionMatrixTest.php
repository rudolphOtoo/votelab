<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\CategoryAbility;
use App\Enums\CategoryPhase;
use App\Enums\NomineeAbility;
use App\Enums\OrganizationAbility;
use App\Enums\OrganizationRole;
use App\Enums\ReportingAbility;
use App\Support\PermissionMatrix;

/**
 * @return array<string, array{owner: string, admin: string, staff: string}>
 */
function approvedMatrix(): array
{
    static $matrix = null;

    return $matrix ??= require __DIR__ . '/approved-matrix.php';
}

function approved(string $ability, OrganizationRole $role, CategoryPhase $phase): bool
{
    $grant = approvedMatrix()[$ability][$role->value];

    return $grant === '*' || $grant !== '' && str_contains($grant, strtoupper($phase->value[0]));
}

it('lists every ability exactly once, with nothing extra', function (): void {
    $abilities = array_map(static fn(Ability $a): string => $a->value, PermissionMatrix::abilities());

    expect(array_keys(PermissionMatrix::table()))
        ->toEqualCanonicalizing($abilities)
        ->and(array_keys(approvedMatrix()))
        ->toEqualCanonicalizing($abilities);
});

it('grants exactly the approved cells, in every phase', function (Ability $ability): void {
    foreach (OrganizationRole::cases() as $role) {
        foreach (CategoryPhase::cases() as $phase) {
            expect(PermissionMatrix::allows($role, $ability, $phase))
                ->toBe(
                    approved($ability->value, $role, $phase),
                    "{$ability->value} for {$role->value} in {$phase->value}",
                );
        }
    }
})->with(PermissionMatrix::abilities());

it('refuses a phase-limited grant when asked without a phase (fail closed)', function (): void {
    expect(PermissionMatrix::allows(OrganizationRole::Owner, CategoryAbility::CategoryUpdatePricing))
        ->toBeFalse()
        ->and(PermissionMatrix::allows(OrganizationRole::Owner, NomineeAbility::NomineeDelete))
        ->toBeTrue();
});

it('grants ledger writes to nobody, owners included', function (OrganizationRole $role): void {
    expect(PermissionMatrix::allows($role, ReportingAbility::LedgerWrite, CategoryPhase::Draft))->toBeFalse();
})->with(OrganizationRole::cases());

it('lets staff change only nominee photos and bios once voting is live', function (): void {
    $staffLive = array_values(array_filter(
        PermissionMatrix::abilities(),
        static fn(Ability $a): bool => (
            !str_ends_with($a->value, '.view')
            && $a !== OrganizationAbility::MembersLeave
            && $a !== ReportingAbility::ResultsView
            && PermissionMatrix::allows(OrganizationRole::Staff, $a, CategoryPhase::Live)
        ),
    ));

    expect($staffLive)->toBe([NomineeAbility::NomineeUpdateProfile]);
});

it('keeps the README matrix identical to the code', function (): void {
    expect((string) file_get_contents(base_path('README.md')))->toContain(PermissionMatrix::markdown());
});
