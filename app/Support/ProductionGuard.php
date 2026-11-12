<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Security\PhoneHasher;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Production refuses to start (web and CLI) while any of these is wrong (D-106), listing every problem:
 * - the cache store can't hold atomic locks shared by all servers (payment fulfilment and leaderboard
 *   rebuilds depend on them; `array` and `file` don't qualify);
 * - APP_DEBUG is on (stack traces would leak);
 * - the Paystack secret is missing, or isn't a live key (PaystackKeyMode);
 * - VOTING_PHONE_HASH_KEY is missing (D-101);
 * - PayZephyr's transaction log is on (it needs a table VoteLab doesn't create; D-122).
 */
final class ProductionGuard
{
    private const array UNSHARED_STORES = ['array', 'file', 'null'];

    /**
     * @return list<string> Problems; empty when the configuration is safe for production.
     */
    public static function problems(): array
    {
        $problems = [];
        $store = (string) config('cache.default');
        $driver = (string) config("cache.stores.{$store}.driver");

        if (
            in_array($driver, self::UNSHARED_STORES, true) || !Cache::store($store)->getStore() instanceof LockProvider
        ) {
            $problems[] = "CACHE_STORE \"{$store}\" ({$driver}) can't hold atomic locks shared across servers; use redis, database, memcached or dynamodb.";
        }

        if ((bool) config('app.debug')) {
            $problems[] = 'APP_DEBUG must be false in production.';
        }

        if ((string) config('payments.providers.paystack.secret_key') === '') {
            $problems[] = 'PAYSTACK_SECRET_KEY is missing.';
        } elseif (!PaystackKeyMode::matchesEnvironment()) {
            $problems[] = PaystackKeyMode::explanation();
        }

        if (!is_string(config('voting.phone_hash_key')) || config('voting.phone_hash_key') === '') {
            $problems[] = 'VOTING_PHONE_HASH_KEY is missing.';
        }

        if ((bool) config('payments.logging.enabled')) {
            $problems[] = 'PAYMENTS_LOGGING_ENABLED must be false: VoteLab keeps its own ledger and has no payment_transactions table.';
        }

        return $problems;
    }

    /**
     * @throws RuntimeException When anything is wrong.
     */
    public static function enforce(): void
    {
        $problems = self::problems();

        if ($problems !== []) {
            throw new RuntimeException("VoteLab refuses to start in production:\n- " . implode("\n- ", $problems));
        }

        PhoneHasher::ensureConfigured();
    }
}
