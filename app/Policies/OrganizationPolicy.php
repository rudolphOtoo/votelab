<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationAbility;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Organization and membership abilities (App\Support\PermissionMatrix). Password confirmation, owner
 * emails and the last-owner guard are enforced by the actions, not here.
 */
final class OrganizationPolicy
{
    use ChecksPermissionMatrix;

    public function view(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::OrganizationView);
    }

    public function updateProfile(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::OrganizationUpdateProfile);
    }

    public function updateSlug(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::OrganizationUpdateSlug);
    }

    public function updatePayoutSettings(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::OrganizationUpdatePayoutSettings);
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::MembersView);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::MembersManage);
    }

    public function leave(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, OrganizationAbility::MembersLeave);
    }
}
