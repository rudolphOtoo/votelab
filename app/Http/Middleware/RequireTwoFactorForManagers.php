<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owners and admins must turn on two-factor within config('organizations.two_factor_grace_days') of
 * their first sign-in. During the grace period the dashboard shows the deadline; after it, every dashboard
 * page redirects to the security page (which this middleware does not guard) until two-factor is on.
 */
final class RequireTwoFactorForManagers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User || $user->hasConfirmedTwoFactor() || !$user->managesAnyOrganization()) {
            return $next($request);
        }

        $deadline = self::deadline($user);

        if ($deadline->isFuture()) {
            View::share('twoFactorDeadline', $deadline);

            return $next($request);
        }

        return redirect()
            ->route('dashboard.security')
            ->with('status', 'Owners and admins must turn on two-factor authentication to use the dashboard.');
    }

    public static function deadline(User $user): Carbon
    {
        $start = $user->first_signed_in_at ?? now();

        return $start->copy()->addDays((int) config('organizations.two_factor_grace_days'));
    }
}
