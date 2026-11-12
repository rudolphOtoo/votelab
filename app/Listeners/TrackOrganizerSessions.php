<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\SessionEpoch;
use Illuminate\Auth\Events\Login;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

/**
 * Records first sign-in (starts the two-factor grace period), stamps each session with the user's session
 * epoch, and signs out other sessions once two-factor is turned on.
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
    }
}
