<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit;
use App\Support\SessionEpoch;
use Illuminate\Auth\Events\Login;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Records first sign-in (starts the two-factor grace period), stamps each session with the user's session
 * epoch, and signs out other sessions (and other devices' remember-me cookies) whenever two-factor is turned
 * on or off. Both changes are written to the audit log. Fortify's routes for them require a recently
 * confirmed password (config/fortify.php: twoFactorAuthentication.confirmPassword).
 */
final class TrackOrganizerSessions
{
    public function handleLogin(Login $event): void
    {
        if (!$event->user instanceof User) {
            return;
        }

        if ($event->user->first_signed_in_at === null) {
            $event->user->forceFill(['first_signed_in_at' => now()])->save();
        }

        SessionEpoch::remember($event->user);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        SessionEpoch::advance($event->user);

        Audit::record('two_factor.enabled', $event->user, $event->user, [
            'before' => ['two_factor' => false],
            'after' => ['two_factor' => true],
        ]);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        SessionEpoch::advance($event->user);

        Audit::record('two_factor.disabled', $event->user, $event->user, [
            'before' => ['two_factor' => true],
            'after' => ['two_factor' => false],
        ]);
    }
}
