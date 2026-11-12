<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Stage 2a abilities for organization membership. Stage 2b folds these into the role × ability matrix.
 * Every ability denies non-members, so a user from another organization never passes.
 */
final class OrganizationPolicy
{
    /**
     * See the organization's dashboard, members and pending invitations.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }

    /**
     * Invite, revoke invitations, change roles and remove members: owners only.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === OrganizationRole::Owner;
    }

    /**
     * Leave the organization (the last owner can't; RemoveMemberAction enforces that).
     */
    public function leave(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) !== null;
    }
}
