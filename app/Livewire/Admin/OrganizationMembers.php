<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Organizations\ChangeMemberRoleAction;
use App\Actions\Organizations\InviteMemberAction;
use App\Actions\Organizations\RemoveMemberAction;
use App\Actions\Organizations\RevokeInvitationAction;
use App\Enums\OrganizationRole;
use App\Exceptions\Organizations\LastOwnerException;
use App\Models\Organization;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Members and pending invitations of one organization. Any member may view and leave; only owners invite,
 * revoke, change roles and remove. Inviting an owner needs a recently confirmed password.
 */
#[Layout('layouts::dashboard')]
final class OrganizationMembers extends Component
{
    #[Locked]
    public int $organizationId;

    public string $email = '';

    public string $role = 'staff';

    public ?string $notice = null;

    /** members | settings (settings only for someone who may change at least one of them). */
    #[Url]
    public string $tab = 'members';

    public function mount(Organization $organization): void
    {
        Gate::authorize('viewMembers', $organization);

        $this->organizationId = $organization->id;
    }

    public function invite(InviteMemberAction $invite): void
    {
        $organization = $this->organization();
        Gate::authorize('manageMembers', $organization);

        $this->email = trim($this->email);
        $this->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ]);
        $role = OrganizationRole::from($this->role);

        if ($role === OrganizationRole::Owner && !Organizer::passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('dashboard.members', $organization));
            $this->redirectRoute('password.confirm');

            return;
        }

        $invite->execute($organization, $this->email, $role, Organizer::user());

        $this->notice = "Invitation sent to {$this->email}.";
        $this->reset('email', 'role');
    }

    public function revoke(int $invitationId, RevokeInvitationAction $revoke): void
    {
        $organization = $this->organization();
        Gate::authorize('manageMembers', $organization);

        $invitation = $organization->invitations()->findOrFail($invitationId);
        $revoke->execute($invitation, Organizer::user());

        $this->notice = 'Invitation withdrawn.';
    }

    public function changeRole(int $userId, string $role, ChangeMemberRoleAction $change): void
    {
        $organization = $this->organization();
        Gate::authorize('manageMembers', $organization);

        $member = $organization->users()->findOrFail($userId);
        $newRole = OrganizationRole::tryFrom($role) ?? abort(422);

        if ($newRole === OrganizationRole::Owner && !Organizer::passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('dashboard.members', $organization));
            $this->redirectRoute('password.confirm');

            return;
        }

        $this->guardLastOwner(static fn() => $change->execute($organization, $member, $newRole, Organizer::user()));
    }

    public function remove(int $userId, RemoveMemberAction $remove): void
    {
        $organization = $this->organization();
        Gate::authorize('manageMembers', $organization);

        $member = $organization->users()->findOrFail($userId);
        $this->guardLastOwner(static fn() => $remove->execute($organization, $member, Organizer::user()));
    }

    public function leave(RemoveMemberAction $remove): void
    {
        $organization = $this->organization();
        Gate::authorize('leave', $organization);

        $user = Organizer::user();

        if ($this->guardLastOwner(static fn() => $remove->execute($organization, $user, $user))) {
            $this->redirectRoute('dashboard');
        }
    }

    public function render(): View
    {
        $organization = $this->organization();
        $user = Organizer::user();

        return view('livewire.admin.organization-members', [
            'organization' => $organization,
            'members' => $organization->users()->orderBy('name')->get(),
            'invitations' => $organization->invitations()->pending()->latest('id')->get(),
            'canManage' => $user->can('manageMembers', $organization),
            'canSettings' =>
                $canSettings =
                    $user->can('updateProfile', $organization)
                    || $user->can('updateSlug', $organization)
                    || $user->can('updatePayoutSettings', $organization),
            'current' => $canSettings && $this->tab === 'settings' ? 'settings' : 'members',
            'roles' => OrganizationRole::cases(),
        ])->title("Members · {$organization->name}");
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        Gate::authorize('viewMembers', $organization); // Re-checked on every request, not only at mount.

        return $organization;
    }

    /**
     * @param  callable(): void  $change
     */
    private function guardLastOwner(callable $change): bool
    {
        try {
            $change();
        } catch (LastOwnerException $e) {
            $this->addError('members', $e->getMessage());

            return false;
        }

        $this->notice = 'Membership updated.';

        return true;
    }
}
