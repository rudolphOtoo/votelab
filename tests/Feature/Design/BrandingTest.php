<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\Design\Branding;
use App\Support\Design\Contrast;

it('uses the VoteLab brand without an organization colour', function (): void {
    $palette = Branding::for(Organization::factory()->create(['settings' => []]));

    expect($palette->brand)->toBe(Branding::VOTELAB_BRAND)->and($palette->custom)->toBeFalse();
    expect(Branding::for(null))->toEqual(Branding::votelab());
});

it('ignores a malformed colour', function (): void {
    $organization = Organization::factory()->create(['settings' => ['brand_color' => 'red; } body { display:none']]);

    expect(Branding::for($organization)->custom)->toBeFalse();
});

it('puts white text on a dark brand colour', function (): void {
    $palette = Branding::for(Organization::factory()->create(['settings' => ['brand_color' => '#8B1E3F']]));

    expect($palette->brand)
        ->toBe('#8b1e3f')
        ->and($palette->foreground)
        ->toBe('#ffffff')
        ->and($palette->custom)
        ->toBeTrue()
        ->and(Contrast::ratio($palette->foreground, $palette->hover))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->text, '#faf9f6'))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->text, $palette->subtle))
        ->toBeGreaterThanOrEqual(4.5);
});

it('puts dark text on a light brand colour and darkens brand-coloured text until it is readable', function (): void {
    $palette = Branding::fromColor('#f2b705');

    expect($palette->foreground)
        ->toBe('#18181b')
        ->and(Contrast::ratio($palette->foreground, $palette->brand))
        ->toBeGreaterThanOrEqual(4.5)
        ->and($palette->text)
        ->not
        ->toBe('#f2b705')
        ->and(Contrast::ratio($palette->text, '#faf9f6'))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->text, $palette->subtle))
        ->toBeGreaterThanOrEqual(4.5);
});

it('falls back to VoteLab when no text colour is readable on the brand', function (): void {
    expect(Branding::fromColor('#7a7a7a'))->toEqual(Branding::votelab());
});

it('emits CSS declarations only for a custom palette', function (): void {
    $organization = Organization::factory()->create(['settings' => ['brand_color' => '#8b1e3f']]);

    expect(Blade::render('<x-ui.brand-style :organization="$o" />', ['o' => $organization]))
        ->toContain('--brand: #8b1e3f;')
        ->toContain('--brand-fg: #ffffff;')
        ->and(trim(Blade::render('<x-ui.brand-style :organization="null" />')))
        ->toBeEmpty();
});

it('gives every accepted brand colour readable button labels and brand text', function (string $color): void {
    $palette = Branding::fromColor($color);

    expect(Contrast::ratio($palette->foreground, $palette->brand))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->foreground, $palette->hover))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->text, '#faf9f6'))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(Contrast::ratio($palette->text, $palette->subtle))
        ->toBeGreaterThanOrEqual(4.5);
})->with([
    '#000000',
    '#ffffff',
    '#ff0000',
    '#00ff00',
    '#0000ff',
    '#ffff00',
    '#f2b705',
    '#8b1e3f',
    '#1d4ed8',
    '#e11d48',
    '#22c55e',
    '#7a7a7a',
    '#5b5b5b',
    '#a3a3a3',
]);
