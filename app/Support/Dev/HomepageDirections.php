<?php

declare(strict_types=1);

namespace App\Support\Dev;

use App\Support\Design\Contrast;
use LogicException;

/**
 * The three visual directions for the homepage, Step A of the homepage brief (plan/SITE.md): local-only previews at
 * /dev/homepage/{a|b|c}. Each palette remaps the design-system tokens (theme.css) for its page and adds hero tokens;
 * the pairs below are every text, button and UI colour combination the preview uses, checked for WCAG 2.2 AA by
 * tests/Feature/Dev/HomepageDirectionsTest.php. The chosen direction moves into theme.css in Step B.
 */
final class HomepageDirections
{
    /**
     * @var array<string, array{name: string, mood: string, reasoning: string, type: string, colors: array<string, string>, pairs: list<array{0: string, 1: string, 2: string, 3: float}>}>
     */
    private const array DIRECTIONS = [
        'a' => [
            'name' => 'Stage night',
            'mood' => 'Dark, dramatic, gold and light',
            'type' => 'Instrument Sans 600 at 40–72 px for the headline, tight tracking; 400 body at 17 px; small caps-style eyebrow at 13 px with wide tracking.',
            'reasoning' => 'The homepage opens the way an awards night does: house lights down, a warm spotlight, gold on near-black. It reuses the stage screen\'s basalt and gold, so the site, the share images and the projector feel like one show. The drama stays in the hero; everything a voter reads or taps afterwards sits on calm light surfaces, which is where trust comes from.',
            'colors' => [
                'hero-bg' => '#121316',
                'hero-raised' => '#1d2024',
                'hero-text' => '#f5f5f4',
                'hero-muted' => '#b8b1aa',
                'hero-accent' => '#fbbf24',
                'hero-accent-fg' => '#121316',
                'hero-field' => '#1d2024',
                'hero-field-border' => '#8a847d',
                'hero-focus' => '#fbbf24',
                'bg-canvas' => '#faf9f6',
                'bg-surface' => '#ffffff',
                'bg-subtle' => '#f3f1ec',
                'text-primary' => '#18181b',
                'text-secondary' => '#57534e',
                'border-hairline' => '#e4e2dc',
                'brand' => '#121316',
                'brand-hover' => '#2b2e34',
                'brand-fg' => '#fbbf24',
                'brand-text' => '#7c4a03',
                'focus-ring' => '#121316',
                'pill-bg' => '#fdf1d3',
                'pill-fg' => '#7c4a03',
            ],
            'pairs' => [
                ['Headline on hero',            'hero-text',         'hero-bg',     Contrast::AA_TEXT],
                ['Supporting text on hero',     'hero-muted',        'hero-bg',     Contrast::AA_TEXT],
                ['Gold eyebrow on hero',        'hero-accent',       'hero-bg',     Contrast::AA_TEXT],
                ['Search button label',         'hero-accent-fg',    'hero-accent', Contrast::AA_TEXT],
                ['Search text in field',        'hero-text',         'hero-field',  Contrast::AA_TEXT],
                ['Search placeholder in field', 'hero-muted',        'hero-field',  Contrast::AA_TEXT],
                ['Search field border',         'hero-field-border', 'hero-bg',     Contrast::AA_LARGE_OR_UI],
                ['Focus ring on hero',          'hero-focus',        'hero-bg',     Contrast::AA_LARGE_OR_UI],
                ['Card text',                   'text-primary',      'bg-surface',  Contrast::AA_TEXT],
                ['Card secondary text',         'text-secondary',    'bg-surface',  Contrast::AA_TEXT],
                ['Section text on page',        'text-primary',      'bg-canvas',   Contrast::AA_TEXT],
                ['Secondary text on page',      'text-secondary',    'bg-canvas',   Contrast::AA_TEXT],
                ['Button label',                'brand-fg',          'brand',       Contrast::AA_TEXT],
                ['Button label (hover)',        'brand-fg',          'brand-hover', Contrast::AA_TEXT],
                ['Link text on page',           'brand-text',        'bg-canvas',   Contrast::AA_TEXT],
                ['Status pill',                 'pill-fg',           'pill-bg',     Contrast::AA_TEXT],
                ['Focus ring on page',          'focus-ring',        'bg-canvas',   Contrast::AA_LARGE_OR_UI],
            ],
        ],
        'b' => [
            'name' => 'Bright celebration',
            'mood' => 'Light, colourful, energetic',
            'type' => 'Instrument Sans 600 at 40–76 px, set slightly looser and in two colours; 500 for card titles; 400 body at 17 px.',
            'reasoning' => 'This one is the party after the announcement: a warm cream page, a cluster of original geometric shapes in coral, sunflower, teal and violet, and a confident magenta for actions. It reads as joyful and youthful, close to campus and creative awards, and photographs of nominees would sit well inside it. The risk is noise, so colour stays in the shapes and the one action colour, while text stays deep plum on light.',
            'colors' => [
                'hero-bg' => '#fff1e2',
                'hero-raised' => '#ffffff',
                'hero-text' => '#24123a',
                'hero-muted' => '#5d4b6e',
                'hero-accent' => '#b4185a',
                'hero-accent-fg' => '#ffffff',
                'hero-field' => '#ffffff',
                'hero-field-border' => '#7d6c8e',
                'hero-focus' => '#b4185a',
                'shape-coral' => '#ff7a59',
                'shape-sun' => '#ffc83d',
                'shape-teal' => '#14b8a6',
                'shape-violet' => '#7c3aed',
                'bg-canvas' => '#fffaf5',
                'bg-surface' => '#ffffff',
                'bg-subtle' => '#fff1e2',
                'text-primary' => '#24123a',
                'text-secondary' => '#5d4b6e',
                'border-hairline' => '#f0e2d4',
                'brand' => '#b4185a',
                'brand-hover' => '#9a1350',
                'brand-fg' => '#ffffff',
                'brand-text' => '#9a1350',
                'focus-ring' => '#b4185a',
                'pill-bg' => '#ccfbf1',
                'pill-fg' => '#115e59',
            ],
            'pairs' => [
                ['Headline on hero',            'hero-text',         'hero-bg',     Contrast::AA_TEXT],
                ['Headline accent on hero',     'hero-accent',       'hero-bg',     Contrast::AA_TEXT],
                ['Supporting text on hero',     'hero-muted',        'hero-bg',     Contrast::AA_TEXT],
                ['Search button label',         'hero-accent-fg',    'hero-accent', Contrast::AA_TEXT],
                ['Search text in field',        'hero-text',         'hero-field',  Contrast::AA_TEXT],
                ['Search placeholder in field', 'hero-muted',        'hero-field',  Contrast::AA_TEXT],
                ['Search field border',         'hero-field-border', 'hero-field',  Contrast::AA_LARGE_OR_UI],
                ['Focus ring on hero',          'hero-focus',        'hero-bg',     Contrast::AA_LARGE_OR_UI],
                ['Card text',                   'text-primary',      'bg-surface',  Contrast::AA_TEXT],
                ['Card secondary text',         'text-secondary',    'bg-surface',  Contrast::AA_TEXT],
                ['Section text on page',        'text-primary',      'bg-canvas',   Contrast::AA_TEXT],
                ['Secondary text on page',      'text-secondary',    'bg-canvas',   Contrast::AA_TEXT],
                ['Button label',                'brand-fg',          'brand',       Contrast::AA_TEXT],
                ['Button label (hover)',        'brand-fg',          'brand-hover', Contrast::AA_TEXT],
                ['Link text on page',           'brand-text',        'bg-canvas',   Contrast::AA_TEXT],
                ['Status pill',                 'pill-fg',           'pill-bg',     Contrast::AA_TEXT],
                ['Focus ring on page',          'focus-ring',        'bg-canvas',   Contrast::AA_LARGE_OR_UI],
            ],
        ],
        'c' => [
            'name' => 'Modern editorial',
            'mood' => 'Typographic, minimal, confident',
            'type' => 'Instrument Sans 600 at 44–96 px with tight leading for the headline, set against a 12-column grid; numbered section labels in 500 at 13 px; 400 body at 18 px with generous line height.',
            'reasoning' => 'The calmest of the three: no illustration, just large, tightly set type, hairline rules and numbered sections, like a well-made programme booklet. Trust comes from restraint and clarity, and the organizations\' own logos and banners become the colour on the page. VoteLab teal carries every action and a single gold rule marks the winners. It ages best, but it leans on good content: with only one live program it can feel quiet.',
            'colors' => [
                'hero-bg' => '#f3f1ec',
                'hero-raised' => '#ffffff',
                'hero-text' => '#141414',
                'hero-muted' => '#57534e',
                'hero-accent' => '#0e6b5e',
                'hero-accent-fg' => '#ffffff',
                'hero-field' => '#ffffff',
                'hero-field-border' => '#6e6a64',
                'hero-focus' => '#0e6b5e',
                'gold-rule' => '#b45309',
                'bg-canvas' => '#faf9f6',
                'bg-surface' => '#ffffff',
                'bg-subtle' => '#f3f1ec',
                'text-primary' => '#141414',
                'text-secondary' => '#57534e',
                'border-hairline' => '#d6d3cd',
                'brand' => '#0e6b5e',
                'brand-hover' => '#0b574c',
                'brand-fg' => '#ffffff',
                'brand-text' => '#0e6b5e',
                'focus-ring' => '#0e6b5e',
                'pill-bg' => '#e7f2ef',
                'pill-fg' => '#0b574c',
            ],
            'pairs' => [
                ['Headline on hero',            'hero-text',         'hero-bg',     Contrast::AA_TEXT],
                ['Supporting text on hero',     'hero-muted',        'hero-bg',     Contrast::AA_TEXT],
                ['Teal emphasis on hero',       'hero-accent',       'hero-bg',     Contrast::AA_TEXT],
                ['Search button label',         'hero-accent-fg',    'hero-accent', Contrast::AA_TEXT],
                ['Search text in field',        'hero-text',         'hero-field',  Contrast::AA_TEXT],
                ['Search placeholder in field', 'hero-muted',        'hero-field',  Contrast::AA_TEXT],
                ['Search field border',         'hero-field-border', 'hero-field',  Contrast::AA_LARGE_OR_UI],
                ['Focus ring on hero',          'hero-focus',        'hero-bg',     Contrast::AA_LARGE_OR_UI],
                ['Gold rule on page',           'gold-rule',         'bg-canvas',   Contrast::AA_LARGE_OR_UI],
                ['Card text',                   'text-primary',      'bg-surface',  Contrast::AA_TEXT],
                ['Card secondary text',         'text-secondary',    'bg-surface',  Contrast::AA_TEXT],
                ['Section text on page',        'text-primary',      'bg-canvas',   Contrast::AA_TEXT],
                ['Secondary text on page',      'text-secondary',    'bg-canvas',   Contrast::AA_TEXT],
                ['Button label',                'brand-fg',          'brand',       Contrast::AA_TEXT],
                ['Button label (hover)',        'brand-fg',          'brand-hover', Contrast::AA_TEXT],
                ['Link text on page',           'brand-text',        'bg-canvas',   Contrast::AA_TEXT],
                ['Status pill',                 'pill-fg',           'pill-bg',     Contrast::AA_TEXT],
                ['Focus ring on page',          'focus-ring',        'bg-canvas',   Contrast::AA_LARGE_OR_UI],
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DIRECTIONS);
    }

    /**
     * @return array{name: string, mood: string, reasoning: string, type: string, colors: array<string, string>, pairs: list<array{0: string, 1: string, 2: string, 3: float}>}|null
     */
    public static function find(string $key): ?array
    {
        return self::DIRECTIONS[$key] ?? null;
    }

    /**
     * The palette as CSS custom properties, for the page's root element.
     */
    public static function cssVariables(string $key): string
    {
        $colors = self::DIRECTIONS[$key]['colors'] ?? [];

        return implode(' ', array_map(
            static fn(string $name, string $hex): string => "--{$name}: {$hex};",
            array_keys($colors),
            $colors,
        ));
    }

    /**
     * Every pair with its measured ratio, for the preview's palette panel and the AA test.
     *
     * @return list<array{usage: string, foreground: string, background: string, ratio: float, minimum: float}>
     */
    public static function pairs(string $key): array
    {
        $direction = self::DIRECTIONS[$key];

        return array_map(static function (array $pair) use ($direction): array {
            [$usage, $fg, $bg, $minimum] = $pair;
            $foreground = self::color($direction['colors'], $fg, $usage);
            $background = self::color($direction['colors'], $bg, $usage);

            return [
                'usage' => $usage,
                'foreground' => $foreground,
                'background' => $background,
                'ratio' => Contrast::ratio($foreground, $background),
                'minimum' => $minimum,
            ];
        }, $direction['pairs']);
    }

    /**
     * @param  array<string, string>  $colors
     */
    private static function color(array $colors, string $name, string $usage): string
    {
        return $colors[$name] ?? throw new LogicException("\"{$usage}\" names a colour its palette lacks: {$name}");
    }
}
