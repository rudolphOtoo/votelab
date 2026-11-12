<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * An organizer without two-factor whose first sign-in was $daysAgo days ago.
 */
function withoutTwoFactor(OrganizationRole $role, int $daysAgo): User
{
    $user = User::factory()->create(['first_signed_in_at' => now()->subDays($daysAgo)]);
    Organization::factory()->create()->addMember($user, $role);

    return $user;
}

it('lets owners and admins in during the grace period, with the deadline shown', function (OrganizationRole $role): void {
    $user = withoutTwoFactor($role, daysAgo: 6);

    $this
        ->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('must turn on two-factor authentication by')
        ->assertSee(now()->subDays(6)->addDays(7)->format('j M Y'));
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('blocks the dashboard for owners and admins once the grace period is over', function (OrganizationRole $role): void {
    $user = withoutTwoFactor($role, daysAgo: 8);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/dashboard/security');

    $organization = $user->organizations()->firstOrFail();
    $this
        ->actingAs($user)
        ->get("/dashboard/organizations/{$organization->slug}/members")
        ->assertRedirect('/dashboard/security');
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('keeps the security page open while the dashboard is blocked', function (): void {
    $user = withoutTwoFactor(OrganizationRole::Owner, daysAgo: 30);

    $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/dashboard/security')
        ->assertOk()
        ->assertSee('Set up two-factor');
});

it('lets owners and admins in once two-factor is on, however long ago they joined', function (): void {
    $user = User::factory()->withTwoFactor()->create(['first_signed_in_at' => now()->subYear()]);
    Organization::factory()->create()->addMember($user, OrganizationRole::Owner);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('must turn on two-factor');
});

it('does not require two-factor from staff', function (): void {
    $user = withoutTwoFactor(OrganizationRole::Staff, daysAgo: 30);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('must turn on two-factor');
});

it('starts the grace period at first sign-in', function (): void {
    $user = User::factory()->create(['first_signed_in_at' => null]);
    Organization::factory()->create()->addMember($user, OrganizationRole::Owner);

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()]);

    expect($user->fresh()?->first_signed_in_at?->isToday())->toBeTrue();
    $this->get('/dashboard')->assertOk();
});

it('refuses Livewire actions from an owner whose grace period ended after the page loaded', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $owner = withoutTwoFactor(OrganizationRole::Owner, daysAgo: 6);
    $organization = $owner->organizations()->firstOrFail();
    $page = $this
        ->actingAs($owner)
        ->get("/dashboard/organizations/{$organization->slug}/members")
        ->assertOk()
        ->getContent();

    // The grace period runs out while the page is still open.
    $owner->forceFill(['first_signed_in_at' => now()->subDays(8)])->save();

    livewireCall($page, 'invite', [
        'email' => 'late@example.com',
        'role' => 'staff',
    ])->assertRedirect('/dashboard/security');

    expect(App\Models\OrganizationInvitation::query()->count())->toBe(0);
    Illuminate\Support\Facades\Mail::assertNothingQueued();
});

it('runs the same Livewire action while the grace period lasts (control for the test above)', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $owner = withoutTwoFactor(OrganizationRole::Owner, daysAgo: 6);
    $organization = $owner->organizations()->firstOrFail();
    $page = $this
        ->actingAs($owner)
        ->get("/dashboard/organizations/{$organization->slug}/members")
        ->assertOk()
        ->getContent();

    livewireCall($page, 'invite', ['email' => 'ontime@example.com', 'role' => 'staff'])->assertOk();

    expect(App\Models\OrganizationInvitation::query()->sole()->email)->toBe('ontime@example.com');
});

it('refuses the dashboard switcher action for an admin past the grace period', function (): void {
    $admin = withoutTwoFactor(OrganizationRole::Admin, daysAgo: 6);
    $second = Organization::factory()->create();
    $second->addMember($admin, OrganizationRole::Admin);
    $page = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

    $admin->forceFill(['first_signed_in_at' => now()->subDays(8)])->save();

    livewireCall($page, 'switchOrganization', params: [$second->id])->assertRedirect('/dashboard/security');
    expect(session(App\Support\CurrentOrganization::SESSION_KEY))->toBeNull();
});
