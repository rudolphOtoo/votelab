<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Exceptions\Organizations\LastOwnerException;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use App\Support\PermissionLookups;
use Illuminate\Support\Facades\DB;

/**
 * Changes a member's role. Demoting the last owner is refused. Changes to one organization's
 * memberships are serialized on its row, so two owners demoting each other can't both succeed.
 */
final readonly class ChangeMemberRoleAction
{
    /**
     * @throws LastOwnerException
     */
    public function execute(Organization $organization, User $member, OrganizationRole $role, User $actor): void
    {
        $previous = DB::transaction(static function () use ($organization, $member, $role): ?OrganizationRole {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            $current = $member->roleIn($organization);

            if ($current === null || $current === $role) {
                return null;
            }

            if ($current === OrganizationRole::Owner && $organization->ownerCount() <= 1) {
                throw LastOwnerException::make();
            }

            $organization->users()->updateExistingPivot($member->id, ['role' => $role->value]);
            PermissionLookups::forgetMemberships();

            return $current;
        });

        if ($previous !== null) {
            Audit::record(
                'membership.role_changed',
                $actor,
                $member,
                ['before' => ['role' => $previous->value], 'after' => ['role' => $role->value]],
                ['organization_id' => $organization->id],
            );
        }
    }
}
