<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Who is voting, as far as abuse controls are concerned. Both values are keyed hashes; neither can be
 * reversed to an IP address, user agent or cookie.
 */
final readonly class VoterIdentity
{
    public function __construct(
        public string $voterKey, // HMAC-SHA256(ip | user agent | device cookie): one browser on one network
        public string $ipHash, // HMAC-SHA256(ip): a whole network, e.g. a campus NAT
    ) {}
}
