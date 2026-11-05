<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\Request;

/**
 * Deterministic, non-reversible voter fingerprints for free-vote limits (plan DEV_B §3).
 *
 * The plan's SHA256(IP + User-Agent + cookie UUID) is keyed (HMAC) so the hashes cannot be brute-forced
 * back to an IP address. The device cookie is set by EnsureVoterCookie; without it, the key falls back
 * to IP + user agent, which is stricter, never looser.
 */
final readonly class VoteFingerprintService
{
    public function identify(Request $request): VoterIdentity
    {
        $ip = (string) $request->ip();
        $cookie = $request->cookie(self::cookieName());

        return new VoterIdentity(
            voterKey: $this->hash(implode('|', [
                $ip,
                (string) $request->userAgent(),
                is_string($cookie) ? $cookie : '',
            ])),
            ipHash: $this->hash($ip),
        );
    }

    public static function cookieName(): string
    {
        return (string) config('voting.voter_cookie');
    }

    private function hash(string $value): string
    {
        $key = config('voting.fingerprint_key');

        return hash_hmac('sha256', $value, is_string($key) && $key !== '' ? $key : (string) config('app.key'));
    }
}
