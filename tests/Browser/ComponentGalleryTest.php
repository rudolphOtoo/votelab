<?php

declare(strict_types=1);

// Advisory group (D-113): screenshots for review at every stop, plus an axe scan of every component at every
// impact level (3 = minor and above).

it('renders the component gallery accessibly at each width', function (int $width, int $height): void {
    visit('/dev/components')
        ->resize($width, $height)
        ->assertSee('VoteLab design system')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        ->screenshot(fullPage: true, filename: "gallery-{$width}");
})->with([
    'phone' => [360, 800],
    'tablet' => [768, 1024],
    'desktop' => [1440, 900],
])->group('advisory', 'screenshots');

it('opens the confirmation sheet as a bottom sheet on a phone', function (): void {
    visit('/dev/components')
        ->resize(360, 800)
        ->click('Close voting…')
        ->assertSee('Close voting in Artiste of the Year?')
        ->assertNoAccessibilityIssues(3)
        ->screenshot(fullPage: false, filename: 'gallery-sheet-360');
})->group('advisory', 'screenshots');

it('renders text in self-hosted Instrument Sans, preloading only the above-the-fold weights', function (): void {
    $page = visit('/dev/components')->resize(360, 800);

    $page->assertSourceHas('rel="preload" as="font"')->assertSourceHas('type="font/woff2"');

    expect($page->script(<<<'JS'
        async () => {
            await document.fonts.ready;
            return {
                regular: document.fonts.check('400 16px "Instrument Sans"'),
                semibold: document.fonts.check('600 16px "Instrument Sans"'),
                woff: performance.getEntriesByType('resource').some((r) => r.name.endsWith('.woff')),
                preloads: document.querySelectorAll('link[rel="preload"][as="font"]').length,
            };
        }
        JS))->toBe(['regular' => true, 'semibold' => true, 'woff' => false, 'preloads' => 2]);
})->group('advisory');
