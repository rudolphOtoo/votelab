<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Organization settings and membership. Grants live in App\Support\PermissionMatrix.
 */
enum OrganizationAbility: string implements Ability
{
    case OrganizationView = 'organization.view';
    case OrganizationUpdateProfile = 'organization.updateProfile';
    case OrganizationUpdateSlug = 'organization.updateSlug';
    case OrganizationUpdatePayoutSettings = 'organization.updatePayoutSettings';
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case MembersLeave = 'members.leave';

    public function description(): string
    {
        return match ($this) {
            self::OrganizationView => 'See the dashboard and programs',
            self::OrganizationUpdateProfile => 'Change name and logo',
            self::OrganizationUpdateSlug => 'Change the URL slug (reserved list; password confirm; owners emailed)',
            self::OrganizationUpdatePayoutSettings
                => 'Change the Paystack subaccount (password confirm; owners emailed)',
            self::MembersView => 'See members and pending invitations',
            self::MembersManage => 'Invite, revoke, change roles, remove (owner invites need password confirm)',
            self::MembersLeave => 'Leave the organization (never the last owner)',
        };
    }
}
