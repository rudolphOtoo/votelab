<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

const AUTHENTICATOR_SEED = 'JBSWY3DPEHPK3PXP';

function currentCode(): string
{
    return app(Google2FA::class)->getCurrentOtp(AUTHENTICATOR_SEED);
}

it('renders every sign-in page with labelled fields', function (string $uri, array $fields): void {
    $html = $this->get($uri)->assertOk()->getContent();

    foreach ($fields as $field) {
        expect($html)->toContain("<label for=\"field-{$field}\"")->toContain("id=\"field-{$field}\"");
    }
})->with([
    'login' => ['/login', ['email', 'password']],
    'forgot password' => ['/forgot-password', ['email']],
    'reset password' => [
        '/reset-password/some-token?email=a%40example.com',
        ['email', 'password', 'password-confirmation'],
    ],
]);

it('renders the two-factor challenge and confirm-password pages', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this
        ->withSession(['login.id' => $user->id])
        ->get('/two-factor-challenge')
        ->assertOk()
        ->assertSee('Authentication code');
    $this->actingAs($user)->get('/user/confirm-password')->assertOk()->assertSee('Confirm it');
});

it('announces errors: summary as an alert, field marked invalid and described by its message', function (): void {
    $user = User::factory()->create();

    $this
        ->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => Str::password(16)])
        ->assertRedirect('/login');

    $html = $this->get('/login')->getContent();
    expect($html)
        ->toContain('role="alert"')
        ->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="field-email-error"');
});

it('signs in with a password and lands on the dashboard', function (): void {
    $user = organizer(OrganizationRole::Staff);

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->first_signed_in_at)->not->toBeNull();
});

it('sends guests from the dashboard to sign in', function (): void {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('asks for a two-factor code, then signs in with it', function (): void {
    $user = User::factory()->withTwoFactor(AUTHENTICATOR_SEED)->create();

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()])->assertRedirect(
        '/two-factor-challenge',
    );
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => currentCode()])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('signs in with a recovery code and uses it up', function (): void {
    $user = User::factory()->withTwoFactor(AUTHENTICATOR_SEED, ['recovery-a', 'recovery-b'])->create();

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()]);
    $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-a'])->assertRedirect('/dashboard');

    expect($user->fresh()?->recoveryCodes())->not->toContain('recovery-a');
});

it('rejects a wrong two-factor code', function (): void {
    $user = User::factory()->withTwoFactor(AUTHENTICATOR_SEED)->create();

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()]);
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});

it('rate limits password attempts (login limiter)', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => Str::password(16)])->assertStatus(302);
    }

    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()])->assertStatus(429);
    $this->assertGuest();
});

it('rate limits two-factor attempts (two-factor limiter)', function (): void {
    $user = User::factory()->withTwoFactor(AUTHENTICATOR_SEED)->create();
    $this->post('/login', ['email' => $user->email, 'password' => factoryPassword()]);

    foreach (range(1, 5) as $_) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }

    $this->post('/two-factor-challenge', ['code' => currentCode()])->assertStatus(429);
    $this->assertGuest();
});

it('uses the login, two-factor and passkeys limiters', function (): void {
    expect(config('fortify.limiters'))->toBe([
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
    ]);
});

it('answers forgot-password identically whether or not the email has an account', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

    foreach ([$known, $unknown] as $response) {
        $response->assertRedirect('/forgot-password')->assertSessionHas(
            'status',
            PasswordResetLinkRequestedResponse::MESSAGE,
        );
        $response->assertSessionHasNoErrors();
    }

    Notification::assertSentTo($user, QueuedResetPassword::class);
    Notification::assertCount(1);
});

it('gives the same answer when a known email is throttled', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    $this
        ->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE)
        ->assertSessionHasNoErrors();
});

it('queues the reset email so response time gives nothing away', function (): void {
    expect(new QueuedResetPassword('token'))->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('resets a password with a valid link and ends other sessions', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $new = Str::password(16);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => $new,
        'password_confirmation' => $new,
    ])->assertRedirect('/login');

    expect($user->fresh()?->session_epoch)->toBe(1);
});
