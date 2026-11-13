<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Deterministic, non-reversible voter fingerprints for free-vote limits (plan DEV_B §3).
 *
 * The plan's SHA256(IP + User-Agent + cookie UUID) is keyed (HMAC) so the hashes cannot be brute-forced
 * back to an IP address. The device cookie is set by EnsureVoterCookie; without it, the key falls back
 * to IP + user agent, which is stricter, never looser.
 */
final readonly class VoteFingerprintService
{
    public const string NON_PRODUCTION_KEY = 'votelab-non-production-fingerprint-key';

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
            deviceKey: is_string($cookie) && $cookie !== '' ? $this->hash('device|' . $cookie) : null,
        );
    }

    public static function cookieName(): string
    {
        return (string) config('voting.voter_cookie');
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    /**
     * The dedicated fingerprint key (D-126): never APP_KEY, so rotating APP_KEY can't reset free-vote
     * allowances. Production refuses to boot without it (ProductionGuard); elsewhere a fixed, public test key
     * is used, so fingerprints made outside production are worthless against production data.
     */
    public static function key(): string
    {
        $key = config('voting.fingerprint_key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('VOTING_FINGERPRINT_KEY must be set in production.');
        }

        return self::NON_PRODUCTION_KEY;
    }
}
