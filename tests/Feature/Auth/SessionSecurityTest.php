<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\SessionEpoch;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

it('signs out a session that predates a credential change', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $user->forceFill(['session_epoch' => 3])->save();

    // This session was stamped before the change (epoch 2).
    $this
        ->actingAs($user)
        ->withSession([SessionEpoch::SESSION_KEY => 2])
        ->get('/dashboard')
        ->assertRedirect('/login')
        ->assertSessionHas('status');

    $this->assertGuest();
});

it('keeps a session stamped with the current epoch', function (): void {
    $user = organizer(OrganizationRole::Staff);

    $this->actingAs($user)->withSession([SessionEpoch::SESSION_KEY => 0])->get('/dashboard')->assertOk();
});

it('ends other sessions when the password changes, but keeps the one that changed it', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $rememberToken = $user->remember_token;
    $new = 'another-long-enough-password';

    $this
        ->actingAs($user)
        ->withSession([SessionEpoch::SESSION_KEY => 0, 'auth.password_confirmed_at' => time()])
        ->put('/user/password', [
            'current_password' => factoryPassword(),
            'password' => $new,
            'password_confirmation' => $new,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->session_epoch)
        ->toBe(1)
        ->and($user->remember_token)
        ->not
        ->toBe($rememberToken)
        ->and(session(SessionEpoch::SESSION_KEY))
        ->toBe(1);

    // This session carries on; another one, still on epoch 0, is signed out.
    $this->get('/dashboard')->assertOk();
    $this->withSession([SessionEpoch::SESSION_KEY => 0])->get('/dashboard')->assertRedirect('/login');
});

it('ends other sessions when two-factor is turned on', function (): void {
    $user = User::factory()->create();

    TwoFactorAuthenticationConfirmed::dispatch($user);

    expect($user->fresh()?->session_epoch)->toBe(1);
});

it('stamps new sign-ins with the current epoch', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $user->forceFill(['session_epoch' => 5])->save();

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()]);

    expect(session(SessionEpoch::SESSION_KEY))->toBe(5);
});
