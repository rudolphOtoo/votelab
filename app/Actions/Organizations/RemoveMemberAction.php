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
 * Removes a member, or lets a member leave (actor = member). Removing or leaving as the last owner is
 * refused; changes are serialized on the organization row (see ChangeMemberRoleAction).
 */
final readonly class RemoveMemberAction
{
    /**
     * @throws LastOwnerException
     */
    public function execute(Organization $organization, User $member, User $actor): void
    {
        $removed = DB::transaction(static function () use ($organization, $member): ?OrganizationRole {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            $current = $member->roleIn($organization);

            if ($current === null) {
                return null;
            }

            if ($current === OrganizationRole::Owner && $organization->ownerCount() <= 1) {
                throw LastOwnerException::make();
            }

            $organization->users()->detach($member->id);
            PermissionLookups::forgetMemberships();

            return $current;
        });

        if ($removed !== null) {
            Audit::record(
                $actor->is($member) ? 'membership.left' : 'membership.removed',
                $actor,
                $member,
                ['before' => ['role' => $removed->value], 'after' => ['role' => null]],
                ['organization_id' => $organization->id],
            );
        }
    }
}
