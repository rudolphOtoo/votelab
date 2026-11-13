<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Helpers for organizer (dashboard) pages.
 */
final class Organizer
{
    /**
     * The signed-in organizer; 403 for anyone else (routes already require auth).
     */
    public static function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Whether this session confirmed its password (or a passkey) within auth.password_timeout.
     */
    public static function passwordRecentlyConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return (time() - $confirmedAt) < (int) config('auth.password_timeout', 10_800);
    }
}
