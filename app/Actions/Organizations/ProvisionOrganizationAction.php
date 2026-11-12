<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Rules\OrganizationSlug;
use App\Support\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bootstraps an organization and its first owner (organizations:create). Idempotent: running it again
 * with the same slug never creates a second organization, never renames one, and never re-sends an owner
 * invitation that is still pending.
 */
final readonly class ProvisionOrganizationAction
{
    public function __construct(
        private InviteMemberAction $invite,
    ) {}

    /**
     * @return array{organization: Organization, created: bool, outcome: ProvisionOutcome}
     *
     * @throws ValidationException When the name, slug or owner email is invalid.
     */
    public function execute(string $name, string $slug, string $ownerEmail): array
    {
        $ownerEmail = Str::lower(trim($ownerEmail));

        Validator::make(['name' => trim($name), 'slug' => $slug, 'owner' => $ownerEmail], [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', new OrganizationSlug()],
            'owner' => ['required', 'email', 'max:255'],
        ])->validate();

        $organization = Organization::query()->firstOrCreate(['slug' => $slug], ['name' => trim($name)]);
        $created = $organization->wasRecentlyCreated;

        if ($created) {
            Audit::record(
                'organization.created',
                null,
                $organization,
                ['before' => [], 'after' => ['slug' => $organization->slug]],
                ['via' => 'cli'],
            );
        }

        $member = $organization->users()->where('email', $ownerEmail)->first();

        if ($member !== null) {
            $outcome = $member->roleIn($organization) === OrganizationRole::Owner
                ? ProvisionOutcome::AlreadyOwner
                : ProvisionOutcome::MemberWithOtherRole;

            return ['organization' => $organization, 'created' => $created, 'outcome' => $outcome];
        }

        $pending = OrganizationInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $ownerEmail)
            ->where('role', OrganizationRole::Owner->value)
            ->pending()
            ->exists();

        if ($pending) {
            return [
                'organization' => $organization,
                'created' => $created,
                'outcome' => ProvisionOutcome::InvitationPending,
            ];
        }

        $this->invite->execute($organization, $ownerEmail, OrganizationRole::Owner, inviter: null);

        return ['organization' => $organization, 'created' => $created, 'outcome' => ProvisionOutcome::Invited];
    }
}
