<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\VoteFingerprintService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives each browser a random device id (part of the free-vote fingerprint). Holds no personal data.
 */
final class EnsureVoterCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = VoteFingerprintService::cookieName();
        $current = $request->cookie($name);

        if (!is_string($current) || $current === '') {
            Cookie::queue(Cookie::make(
                $name,
                (string) Str::uuid(),
                minutes: 60 * 24 * 365,
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $next($request);
    }
}
