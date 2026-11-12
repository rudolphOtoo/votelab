<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\SessionEpoch;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
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

        Log::channel('audit')->info('two_factor.enabled', ['user_id' => $event->user->id]);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        SessionEpoch::advance($event->user);

        Log::channel('audit')->info('two_factor.disabled', ['user_id' => $event->user->id]);
    }
}
