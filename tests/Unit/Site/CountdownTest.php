<?php

declare(strict_types=1);

use App\Support\Site\Countdown;
use Carbon\CarbonImmutable;

/*
 * "Closes in 2d 4h" on the public site's program cards. resources/js/home.js words it the same way and keeps it
 * current. (Moved from the Step A preview tests, D-148.)
 */

it('words the countdown like resources/js/home.js does', function (int $minutes, string $text): void {
    $now = CarbonImmutable::parse('2026-11-14 12:00:00');

    expect(Countdown::closesIn($now->addMinutes($minutes), $now))->toBe($text);
})->with([
    [-5, 'Closing now'],
    [0, 'Closing now'],
    [9, 'Closes in 9m'],
    [192, 'Closes in 3h 12m'],
    [(2 * 1440) + 245, 'Closes in 2d 4h'],
]);
