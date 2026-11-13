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
        public string $ipHash, // HMAC-SHA256(ip): a whole network, e.g. a campus NAT or a carrier's NAT
        public ?string $deviceKey = null, // HMAC-SHA256(device cookie): one browser, whatever its network
    ) {}

    /**
     * The key for per-voter rate limits (D-105): the device cookie when the browser has one, so hundreds of
     * voters behind one venue or carrier IP don't share a limit; before the cookie exists (first request, or
     * cookies blocked), the IP + browser voter key.
     */
    public function rateKey(): string
    {
        return $this->deviceKey !== null ? "device:{$this->deviceKey}" : "voter:{$this->voterKey}";
    }
}
