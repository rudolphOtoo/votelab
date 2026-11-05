<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Applies a named limiter from AppServiceProvider outside route middleware (e.g. in a Livewire action).
 * Every limit is checked before any is hit, so a blocked request never consumes allowance.
 */
final class Throttle
{
    /**
     * @return int|null Seconds until retry when blocked, or null when allowed (and counted).
     */
    public static function attempt(string $limiter, mixed ...$context): ?int
    {
        $resolver = RateLimiter::limiter($limiter);

        if ($resolver === null) {
            throw new InvalidArgumentException("Rate limiter [{$limiter}] is not defined.");
        }

        $limits = $resolver(...$context);
        $limits = $limits instanceof Limit ? [$limits] : $limits;

        if (!is_array($limits)) {
            throw new InvalidArgumentException("Rate limiter [{$limiter}] must return limits.");
        }

        foreach ($limits as $limit) {
            $key = self::key($limiter, $limit);

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                return RateLimiter::availableIn($key);
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit(self::key($limiter, $limit), $limit->decaySeconds);
        }

        return null;
    }

    private static function key(string $limiter, Limit $limit): string
    {
        return "{$limiter}:{$limit->key}";
    }
}
