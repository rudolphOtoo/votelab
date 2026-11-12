<?php

declare(strict_types=1);

use App\Actions\Organizations\ChangeMemberRoleAction;
use App\Actions\Organizations\RemoveMemberAction;
use App\Enums\OrganizationRole;
use App\Exceptions\Organizations\LastOwnerException;
use App\Livewire\Admin\OrganizationMembers;
use App\Models\Organization;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use Livewire\Livewire;

function membersOf(User $user, Organization $organization): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($user)->test(OrganizationMembers::class, ['organization' => $organization]);
}

it('never lets an organization lose its last owner: demotion, removal or leaving', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    expect(fn() => app(ChangeMemberRoleAction::class)->execute($organization, $owner, OrganizationRole::Admin, $owner))
        ->toThrow(LastOwnerException::class)
        ->and(fn() => app(RemoveMemberAction::class)->execute($organization, $owner, $owner))
        ->toThrow(LastOwnerException::class);

    membersOf($owner, $organization)->call('leave')->assertHasErrors('members')->assertNoRedirect();
    membersOf($owner, $organization)->call('changeRole', $owner->id, 'staff')->assertHasErrors('members');

    expect($owner->roleIn($organization))->toBe(OrganizationRole::Owner)->and($organization->ownerCount())->toBe(1);
});

it('allows demoting or removing an owner while another owner remains', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $coOwner = organizer(OrganizationRole::Owner, $organization);

    membersOf($owner, $organization)->call('changeRole', $coOwner->id, 'admin')->assertHasNoErrors();
    expect($coOwner->roleIn($organization))->toBe(OrganizationRole::Admin);

    // Now the only owner again: can't step down.
    membersOf($owner, $organization)->call('changeRole', $owner->id, 'admin')->assertHasErrors('members');
});

it('lets a second owner leave', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $coOwner = organizer(OrganizationRole::Owner, $organization);

    membersOf($coOwner, $organization)->call('leave')->assertRedirect('/dashboard');

    expect($coOwner->roleIn($organization))->toBeNull()->and($organization->ownerCount())->toBe(1);
});

it('lets staff and admins leave', function (OrganizationRole $role): void {
    $organization = organizer()->organizations()->firstOrFail();
    $member = organizer($role, $organization);

    membersOf($member, $organization)->call('leave')->assertRedirect('/dashboard');

    expect($member->roleIn($organization))->toBeNull();
})->with([OrganizationRole::Admin, OrganizationRole::Staff]);

it('requires a recently confirmed password to make someone an owner', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $admin = organizer(OrganizationRole::Admin, $organization);

    membersOf($owner, $organization)
        ->call('changeRole', $admin->id, 'owner')
        ->assertRedirect(route('password.confirm'));
    expect($admin->roleIn($organization))->toBe(OrganizationRole::Admin);

    confirmPassword();
    membersOf($owner, $organization)->call('changeRole', $admin->id, 'owner');
    expect($admin->roleIn($organization))->toBe(OrganizationRole::Owner);
});

it('audits role changes and removals', function (): void {
    $audit = captureAuditLog();
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $staff = organizer(OrganizationRole::Staff, $organization);

    membersOf($owner, $organization)->call('changeRole', $staff->id, 'admin')->call('remove', $staff->id);

    expect($audit->hasInfoThatContains('membership.role_changed'))
        ->toBeTrue()
        ->and($audit->hasInfoThatContains('membership.removed'))
        ->toBeTrue();
});

it('lets only owners change roles or remove members', function (OrganizationRole $role): void {
    $organization = organizer()->organizations()->firstOrFail();
    $member = organizer($role, $organization);
    $staff = organizer(OrganizationRole::Staff, $organization);

    membersOf($member, $organization)->call('changeRole', $staff->id, 'admin')->assertForbidden();
    membersOf($member, $organization)->call('remove', $staff->id)->assertForbidden();

    expect($staff->roleIn($organization))->toBe(OrganizationRole::Staff);
})->with([OrganizationRole::Admin, OrganizationRole::Staff]);

it('re-checks membership on every request, not only when the page opened', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $page = membersOf($owner, $organization);

    $organization->users()->detach($owner->id);

    $page->call('remove', $owner->id)->assertForbidden();
});

it('denies every ability to a user from another organization', function (string $ability): void {
    $organization = organizer()->organizations()->firstOrFail();
    $outsider = organizer(); // Owner elsewhere.

    expect(app(OrganizationPolicy::class)->{$ability}($outsider, $organization))->toBeFalse();
    $this->actingAs($outsider)->get("/dashboard/organizations/{$organization->slug}/members")->assertForbidden();
})->with(['view', 'manageMembers', 'leave']);

it('grants abilities by role', function (OrganizationRole $role, bool $view, bool $manage, bool $leave): void {
    $organization = organizer()->organizations()->firstOrFail();
    $user = organizer($role, $organization);
    $policy = app(OrganizationPolicy::class);

    expect($policy->view($user, $organization))
        ->toBe($view)
        ->and($policy->manageMembers($user, $organization))
        ->toBe($manage)
        ->and($policy->leave($user, $organization))
        ->toBe($leave);
})->with([
    'owner' => [OrganizationRole::Owner, true, true, true],
    'admin' => [OrganizationRole::Admin, true, false, true],
    'staff' => [OrganizationRole::Staff, true, false, true],
]);
