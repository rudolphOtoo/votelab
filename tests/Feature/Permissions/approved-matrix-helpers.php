<?php

declare(strict_types=1);

// Shared by PermissionMatrixTest and PolicyTest. A plain file, not a *Test.php: requiring a test file from
// another test registers its tests again, so in parallel runs they ran twice.

use App\Enums\CategoryPhase;
use App\Enums\OrganizationRole;

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
