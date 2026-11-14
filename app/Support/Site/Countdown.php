<?php

declare(strict_types=1);

namespace App\Support\Site;

use Carbon\CarbonImmutable;

/**
 * "Closes in 2d 4h" for programs open for voting. resources/js/home.js words it the same way and keeps it current.
 */
final class Countdown
{
    public static function closesIn(CarbonImmutable $closesAt, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();
        $minutes = (int) floor(max(0, $closesAt->getTimestamp() - $now->getTimestamp()) / 60);

        return match (true) {
            $minutes < 1 => 'Closing now',
            $minutes < 60 => "Closes in {$minutes}m",
            $minutes < 1_440 => sprintf('Closes in %dh %dm', intdiv($minutes, 60), $minutes % 60),
            default => sprintf('Closes in %dd %dh', intdiv($minutes, 1_440), intdiv($minutes % 1_440, 60)),
        };
    }
}
