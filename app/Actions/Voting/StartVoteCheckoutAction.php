<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Models\Nominee;
use App\Services\Security\VoterIdentity;
use App\Support\Throttle;
use App\Support\VoteKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Starts a paid-vote checkout for one voter, safely under double submits and abuse:
 * the same order submitted again returns the checkout already started; otherwise the voter, network and
 * nominee rate limits apply and one lock per voter and nominee lets only one checkout start at a time.
 */
final readonly class StartVoteCheckoutAction
{
    private const int LOCK_SECONDS = 15;

    private const int REUSE_MINUTES = 10;

    public function __construct(
        private InitiatePaidVoteOrderAction $initiate,
    ) {}

    /**
     * @return string The Paystack authorization URL to send the voter to.
     *
     * @throws CheckoutUnavailableException
     */
    public function execute(Nominee $nominee, int $quantity, ?string $email, VoterIdentity $voter): string
    {
        $email = Str::lower(trim((string) $email));
        $recentKey = VoteKeys::recentCheckout($voter->voterKey, $nominee->id, $quantity, $email);
        $recentUrl = Cache::get($recentKey);

        if (is_string($recentUrl)) {
            return $recentUrl;
        }

        $retryAfter = Throttle::attempt('vote-checkout', $voter, $nominee->id);

        if ($retryAfter !== null) {
            throw CheckoutUnavailableException::tooManyAttempts($retryAfter);
        }

        $lock = Cache::lock(VoteKeys::checkoutLock($voter->voterKey, $nominee->id), self::LOCK_SECONDS);

        if (!$lock->get()) {
            throw CheckoutUnavailableException::alreadyStarting();
        }

        try {
            $url = $this->initiate->execute($nominee, $quantity, $email === '' ? null : $email)->authorizationUrl;
            Cache::put($recentKey, $url, now()->addMinutes(self::REUSE_MINUTES));

            return $url;
        } finally {
            $lock->release();
        }
    }
}
