<?php

declare(strict_types=1);

use App\Support\Design\Contrast;
use App\Support\Design\Tokens;

it('computes WCAG contrast ratios', function (): void {
    expect(Contrast::ratio('#000000', '#ffffff'))
        ->toBe(21.0)
        ->and(Contrast::ratio('#ffffff', '#ffffff'))
        ->toBe(1.0)
        ->and(Contrast::ratio('#767676', '#ffffff'))
        ->toBe(4.54);
});

it('reads every colour pair from theme.css', function (): void {
    foreach (Tokens::pairs() as $pair) {
        expect(Contrast::isHex($pair['foreground']))
            ->toBeTrue("{$pair['usage']} has no foreground token")
            ->and(Contrast::isHex($pair['background']))
            ->toBeTrue("{$pair['usage']} has no background token");
    }

    expect(Tokens::pairs())->toHaveCount(28);
});

it('meets WCAG 2.2 AA for every text and UI colour pair, light and stage', function (): void {
    $failures = array_filter(
        Tokens::pairs(),
        static fn(array $pair): bool => Contrast::ratio($pair['foreground'], $pair['background']) < $pair['minimum'],
    );

    expect(array_column($failures, 'usage'))->toBeEmpty();
});

it('keeps the stage theme overrides separate from the light theme', function (): void {
    expect(Tokens::colors()['bg-canvas'])
        ->toBe('#faf9f6')
        ->and(Tokens::colors("[data-theme='stage']")['bg-canvas'])
        ->toBe('#121316')
        ->and(Tokens::colors("[data-theme='stage']")['success'])
        ->toBe(Tokens::colors()['success']);
});
