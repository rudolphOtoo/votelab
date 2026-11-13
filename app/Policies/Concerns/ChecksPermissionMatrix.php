<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Ability;
use App\Enums\CategoryPhase;
use App\Models\User;
use App\Support\PermissionLookups;
use App\Support\PermissionMatrix;

/**
 * Membership first (non-members, including other organizations' owners, are always refused), then the
 * role's grant in App\Support\PermissionMatrix.
 */
trait ChecksPermissionMatrix
{
    private function granted(User $user, int $organizationId, Ability $ability, ?CategoryPhase $phase = null): bool
    {
        $role = PermissionLookups::role($user, $organizationId);

        return $role !== null && PermissionMatrix::allows($role, $ability, $phase);
    }
}
