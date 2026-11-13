<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Signs out a user's other sessions when their credentials change.
 *
 * Each session remembers the user's session_epoch at sign-in; EnsureSessionIsCurrent signs out any session
 * holding an older value. advance() bumps it (password changed or reset, two-factor turned on), rotates the
 * remember-me token so "remember me" cookies elsewhere stop working, and moves the current session, if it
 * is this user's, to the new value so only the other sessions end. Works with any session driver.
 */
final class SessionEpoch
{
    public const string SESSION_KEY = 'auth.session_epoch';

    public static function remember(User $user): void
    {
        Session::put(self::SESSION_KEY, self::of($user));
    }

    public static function advance(User $user): void
    {
        $user->forceFill([
            'session_epoch' => self::of($user) + 1,
            'remember_token' => Str::random(60),
        ])->save();

        if (Auth::guard('web')->id() === $user->id && Session::isStarted()) {
            self::remember($user);
        }
    }

    /**
     * A freshly created model may not carry the column's database default yet.
     */
    private static function of(User $user): int
    {
        return (int) ($user->getAttribute('session_epoch') ?? 0);
    }

    public static function isCurrent(User $user): bool
    {
        $epoch = Session::get(self::SESSION_KEY);

        return $epoch === null || (int) $epoch === self::of($user);
    }
}
