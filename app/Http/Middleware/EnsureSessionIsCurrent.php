<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SessionEpoch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a session that predates the user's last password change or two-factor activation
 * (App\Support\SessionEpoch). Runs on the web group; guests cost nothing.
 */
final class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        if (!SessionEpoch::isCurrent($user)) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'You were signed out because your security settings changed. Please sign in again.');
        }

        if (!$request->session()->has(SessionEpoch::SESSION_KEY)) {
            SessionEpoch::remember($user); // Sessions from before this check existed.
        }

        return $next($request);
    }
}
