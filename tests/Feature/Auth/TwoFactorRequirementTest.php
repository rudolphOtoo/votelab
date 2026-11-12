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
