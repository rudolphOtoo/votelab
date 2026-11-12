<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Exceptions\Organizations\LastOwnerException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            return $current;
        });

        if ($previous !== null) {
            Log::channel('audit')->info('membership.role_changed', [
                'organization_id' => $organization->id,
                'user_id' => $member->id,
                'from' => $previous->value,
                'to' => $role->value,
                'by_user_id' => $actor->id,
            ]);
        }
    }
}
