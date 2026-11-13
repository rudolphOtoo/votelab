<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Trusted Proxies
 |--------------------------------------------------------------------------
 |
 | Read by Laravel's global TrustProxies middleware. Only a request whose
 | direct peer is listed here may set the client IP and scheme through
 | X-Forwarded-For / -Proto / -Port. Client IPs drive voter fingerprints and
 | rate limits, so an over-broad value lets voters spoof them.
 |
 | TRUSTED_PROXIES:
 |   empty            trust nobody (local development, direct exposure)
 |   10.0.0.5,...     these addresses or CIDR ranges (load balancer, Cloudflare ranges)
 |   *                whichever peer calls; safe only if the origin accepts traffic
 |                    from the proxy alone AND the proxy overwrites X-Forwarded-For
 |
 */

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => match ($proxies) {
        '' => null,
        '*' => '*',
        default => array_values(array_filter(
            array_map(trim(...), explode(',', $proxies)),
            static fn(string $proxy): bool => $proxy !== '',
        )),
    },
];
