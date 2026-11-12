<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\SessionEpoch;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

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

it('requires a recently confirmed password to turn two-factor off', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->delete('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');

    expect($user->fresh()?->hasConfirmedTwoFactor())->toBeTrue();
});

it('turning two-factor off signs out other sessions and remember-me cookies, and is audited', function (): void {
    $audit = captureAuditLog();
    $user = User::factory()->withTwoFactor()->create();
    $rememberToken = $user->remember_token;

    $this
        ->actingAs($user)
        ->withSession([SessionEpoch::SESSION_KEY => 0, 'auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication')
        ->assertSessionHas('status', 'two-factor-authentication-disabled');

    $user->refresh();
    expect($user->hasConfirmedTwoFactor())
        ->toBeFalse()
        ->and($user->session_epoch)
        ->toBe(1)
        ->and($user->remember_token)
        ->not
        ->toBe($rememberToken)
        ->and($audit->hasInfoThatContains('two_factor.disabled'))
        ->toBeTrue();

    // This session carries on; another one, still on epoch 0, is signed out.
    $this->get('/dashboard')->assertOk();
    $this->withSession([SessionEpoch::SESSION_KEY => 0])->get('/dashboard')->assertRedirect('/login');
});

it('rejects a remember-me token issued before two-factor was turned off', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $oldToken = (string) $user->remember_token;
    $provider = auth()->guard('web')->getProvider();
    expect($provider->retrieveByToken($user->id, $oldToken)?->getAuthIdentifier())->toBe($user->id);

    TwoFactorAuthenticationDisabled::dispatch($user);

    expect($provider->retrieveByToken($user->id, $oldToken))->toBeNull();
});

it('audits turning two-factor on', function (): void {
    $audit = captureAuditLog();

    TwoFactorAuthenticationConfirmed::dispatch(User::factory()->create());

    expect($audit->hasInfoThatContains('two_factor.enabled'))->toBeTrue();
});
