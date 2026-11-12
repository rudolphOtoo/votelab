<?php

declare(strict_types=1);

use App\Actions\Organizations\InviteMemberAction;
use App\Enums\OrganizationRole;
use App\Livewire\Admin\OrganizationMembers;
use App\Livewire\Auth\AcceptInvitation;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
});

/**
 * An invitation plus the token from its email, as a recipient would have it.
 *
 * @return array{OrganizationInvitation, string}
 */
function invitation(array $attributes = []): array
{
    $token = Illuminate\Support\Str::random(48);
    $invitation = OrganizationInvitation::factory()->withToken($token)->create($attributes);

    return [$invitation, $token];
}

function acceptPage(
    OrganizationInvitation $invitation,
    #[SensitiveParameter]
    string $token,
): \Livewire\Features\SupportTesting\Testable {
    return Livewire::withQueryParams(['token' => $token])->test(AcceptInvitation::class, ['invitation' => $invitation]);
}

function membersPage(User $user, Organization $organization): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($user)->test(OrganizationMembers::class, ['organization' => $organization]);
}

it('emails a signed, expiring link and stores only a hash of its token', function (): void {
    $audit = captureAuditLog();
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    membersPage($owner, $organization)
        ->set('email', '  New.Person@Example.com ')
        ->set('role', 'admin')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = OrganizationInvitation::query()->sole();
    expect($invitation->email)
        ->toBe('new.person@example.com')
        ->and($invitation->role)
        ->toBe(OrganizationRole::Admin)
        ->and($invitation->invited_by_user_id)
        ->toBe($owner->id)
        ->and($invitation->expires_at->diffInHours(now(), absolute: true))
        ->toBeGreaterThan(71.9);

    Mail::assertQueued(OrganizationInvitationMail::class, static function (OrganizationInvitationMail $mail) use (
        $invitation,
    ): bool {
        parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);

        return (
            $mail->hasTo('new.person@example.com')
            && array_key_exists('signature', $query)
            && array_key_exists('expires', $query)
            && array_key_exists('token', $query)
            && $invitation->matchesToken((string) $query['token'])
            && !str_contains((string) $invitation->token_hash, (string) $query['token'])
        );
    });
    expect($audit->hasInfoThatContains('invitation.created'))->toBeTrue();
});

it('opens the emailed link and refuses a tampered one', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    app(InviteMemberAction::class)->execute($organization, 'guest@example.com', OrganizationRole::Staff, $owner);

    $url = '';
    Mail::assertQueued(OrganizationInvitationMail::class, static function (OrganizationInvitationMail $mail) use (
        &$url,
    ): bool {
        $url = $mail->acceptUrl;

        return true;
    });

    $this->get($url)->assertOk()->assertSee('Create your organizer account');
    $this->get(str_replace('token=', 'token=x', $url))->assertForbidden();
});

it('creates the account, joins with the invited role and signs in', function (): void {
    $audit = captureAuditLog();
    [$invitation, $token] = invitation(['email' => 'ama@example.com', 'role' => OrganizationRole::Admin]);
    $secret = Illuminate\Support\Str::password(16);

    acceptPage($invitation, $token)
        ->set('name', 'Ama Mensah')
        ->set('password', $secret)
        ->set('password_confirmation', $secret)
        ->call('accept')
        ->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'ama@example.com')->sole();
    expect($user->roleIn($invitation->organization))
        ->toBe(OrganizationRole::Admin)
        ->and($user->email_verified_at)
        ->not
        ->toBeNull()
        ->and($invitation->fresh()?->accepted_by_user_id)
        ->toBe($user->id);
    $this->assertAuthenticatedAs($user);
    expect($audit->hasInfoThatContains('invitation.accepted'))->toBeTrue();
});

it('validates the new account', function (): void {
    [$invitation, $token] = invitation();

    acceptPage($invitation, $token)
        ->set('name', '')
        ->set('password', 'short')
        ->set('password_confirmation', 'different')
        ->call('accept')
        ->assertHasErrors(['name', 'password']);

    expect(User::query()->count())->toBe(0);
});

it('works only once', function (): void {
    [$invitation, $token] = invitation(['email' => 'kofi@example.com']);
    $secret = Illuminate\Support\Str::password(16);
    acceptPage($invitation, $token)
        ->set('name', 'Kofi')
        ->set('password', $secret)
        ->set('password_confirmation', $secret)
        ->call('accept');
    auth()->logout();

    acceptPage($invitation, $token)->assertSee('already been used')->assertDontSee('Create your organizer account');
    expect($invitation->organization->users()->count())->toBe(1);
});

it('refuses an expired invitation', function (): void {
    [$invitation, $token] = invitation(['expires_at' => now()->subMinute()]);

    acceptPage($invitation, $token)->assertSee('has expired')->call('accept');

    expect($invitation->fresh()?->accepted_at)->toBeNull()->and(User::query()->count())->toBe(0);
});

it('refuses a revoked invitation', function (): void {
    [$invitation, $token] = invitation(['revoked_at' => now(), 'revoked_reason' => 'revoked']);

    acceptPage($invitation, $token)->assertSee('was withdrawn');
});

it('refuses a wrong token even on a validly shaped link', function (): void {
    [$invitation] = invitation();

    acceptPage($invitation, 'not-the-token')->assertSee('not valid');
});

it('asks an existing account to sign in, then accepts for it', function (): void {
    $user = User::factory()->create(['email' => 'esi@example.com']);
    [$invitation, $token] = invitation(['email' => 'esi@example.com', 'role' => OrganizationRole::Staff]);

    acceptPage($invitation, $token)->assertSee('already has a VoteLab account')->assertSee('Sign in to accept');

    Livewire::actingAs($user)
        ->withQueryParams(['token' => $token])
        ->test(AcceptInvitation::class, ['invitation' => $invitation])
        ->call('accept')
        ->assertRedirect('/dashboard');

    expect($user->roleIn($invitation->organization))->toBe(OrganizationRole::Staff);
});

it('tells a signed-in user with a different email exactly what to do', function (): void {
    $other = User::factory()->create(['email' => 'someone.else@example.com']);
    [$invitation, $token] = invitation(['email' => 'invited@example.com']);

    Livewire::actingAs($other)
        ->withQueryParams(['token' => $token])
        ->test(AcceptInvitation::class, ['invitation' => $invitation])
        ->assertSee('This invitation is for invited@example.com, but you are signed in as someone.else@example.com.')
        ->assertSee('Sign out of someone.else@example.com')
        ->call('accept');

    expect($invitation->organization->users()->count())->toBe(0)->and($invitation->fresh()?->accepted_at)->toBeNull();
});

it('revokes the older pending invitation when the same email is invited again', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $invite = app(InviteMemberAction::class);

    $first = $invite->execute($organization, 'yaw@example.com', OrganizationRole::Staff, $owner);
    $second = $invite->execute($organization, 'YAW@example.com', OrganizationRole::Admin, $owner);

    expect($first->fresh()?->revoked_reason)
        ->toBe(OrganizationInvitation::REASON_SUPERSEDED)
        ->and($first->fresh()?->revoked_by_user_id)
        ->toBe($owner->id)
        ->and($second->fresh()?->isPending())
        ->toBeTrue()
        ->and($organization->invitations()->pending()->count())
        ->toBe(1);
});

it('rejects inviting someone who is already a member', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $member = organizer(OrganizationRole::Staff, $organization);

    membersPage($owner, $organization)
        ->set('email', strtoupper($member->email))
        ->call('invite')
        ->assertHasErrors([
            'email',
        ]);

    expect(OrganizationInvitation::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('rate limits invitations per inviter', function (): void {
    config(['organizations.invitation_rate_limits.per_inviter_per_hour' => 2]);
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $page = membersPage($owner, $organization);

    $page->set('email', 'a@example.com')->call('invite')->assertHasNoErrors();
    $page->set('email', 'b@example.com')->call('invite')->assertHasNoErrors();
    $page->set('email', 'c@example.com')->call('invite')->assertHasErrors(['email']);

    expect(OrganizationInvitation::query()->count())->toBe(2);
});

it('rate limits invitations per organization across inviters', function (): void {
    config(['organizations.invitation_rate_limits.per_organization_per_day' => 1]);
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $coOwner = organizer(OrganizationRole::Owner, $organization);

    membersPage($owner, $organization)->set('email', 'a@example.com')->call('invite')->assertHasNoErrors();
    membersPage($coOwner, $organization)->set('email', 'b@example.com')->call('invite')->assertHasErrors(['email']);
});

it('requires a recently confirmed password to invite an owner', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    membersPage($owner, $organization)
        ->set('email', 'co-owner@example.com')
        ->set('role', 'owner')
        ->call('invite')
        ->assertRedirect(route('password.confirm'));
    expect(OrganizationInvitation::query()->count())->toBe(0);

    confirmPassword();
    membersPage($owner, $organization)
        ->set('email', 'co-owner@example.com')
        ->set('role', 'owner')
        ->call('invite')
        ->assertHasNoErrors();

    expect(OrganizationInvitation::query()->sole()->role)->toBe(OrganizationRole::Owner);
});

it('lets owners revoke a pending invitation, which then stops working', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    [$invitation, $token] = invitation(['organization_id' => $organization->id]);

    membersPage($owner, $organization)->call('revoke', $invitation->id);

    expect($invitation->fresh()?->revoked_by_user_id)->toBe($owner->id);
    acceptPage($invitation->fresh() ?? $invitation, $token)->assertSee('was withdrawn');
});

it('lets only owners invite or revoke', function (OrganizationRole $role): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $member = organizer($role, $organization);
    [$invitation] = invitation(['organization_id' => $organization->id]);

    membersPage($member, $organization)->set('email', 'x@example.com')->call('invite')->assertForbidden();
    membersPage($member, $organization)->call('revoke', $invitation->id)->assertForbidden();

    expect($invitation->fresh()?->isPending())->toBeTrue();
})->with([OrganizationRole::Admin, OrganizationRole::Staff]);

it('denies an owner of another organization: members page and invitations', function (): void {
    $organization = organizer()->organizations()->firstOrFail();
    $outsider = organizer(); // Owner of a different organization.
    [$invitation] = invitation(['organization_id' => $organization->id]);

    $this->actingAs($outsider)->get("/dashboard/organizations/{$organization->slug}/members")->assertForbidden();

    // Even with a component mounted for their own organization, another organization's invitation is out of reach.
    membersPage($outsider, $outsider->organizations()->firstOrFail())
        ->call('revoke', $invitation->id)
        ->assertNotFound();
    expect($invitation->fresh()?->isPending())->toBeTrue();
});
