<?php

declare(strict_types=1);

namespace App\Support\Design;

/**
 * Reads the colour tokens from resources/css/theme.css (the single source) and lists the text/background
 * pairs the components actually use, each with the WCAG 2.2 AA threshold that applies. The component gallery
 * shows them; tests/Feature/Design/TokenContrastTest.php fails if any pair drops below its threshold.
 */
final class Tokens
{
    /**
     * Light theme: [what it's used for, foreground token, background token, minimum ratio].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float}>
     */
    private const array LIGHT_PAIRS = [
        ['Body text on page',             'text-primary',   'bg-canvas',    Contrast::AA_TEXT],
        ['Body text on card',             'text-primary',   'bg-surface',   Contrast::AA_TEXT],
        ['Secondary text on page',        'text-secondary', 'bg-canvas',    Contrast::AA_TEXT],
        ['Secondary text on muted panel', 'text-secondary', 'bg-subtle',    Contrast::AA_TEXT],
        ['Tertiary text on card',         'text-tertiary',  'bg-surface',   Contrast::AA_TEXT],
        ['Tertiary text on muted panel',  'text-tertiary',  'bg-subtle',    Contrast::AA_TEXT],
        ['Primary button label',          'brand-fg',       'brand',        Contrast::AA_TEXT],
        ['Primary button label (hover)',  'brand-fg',       'brand-hover',  Contrast::AA_TEXT],
        ['Brand text / links on page',    'brand-text',     'bg-canvas',    Contrast::AA_TEXT],
        ['Brand badge',                   'brand-text',     'brand-subtle', Contrast::AA_TEXT],
        ['Field border on card',          'border-strong',  'bg-surface',   Contrast::AA_LARGE_OR_UI],
        ['Focus ring on page',            'focus-ring',     'bg-canvas',    Contrast::AA_LARGE_OR_UI],
        ['Success badge',                 'success-fg',     'success-bg',   Contrast::AA_TEXT],
        ['Warning badge',                 'warning-fg',     'warning-bg',   Contrast::AA_TEXT],
        ['Danger badge / error text',     'danger-fg',      'danger-bg',    Contrast::AA_TEXT],
        ['Info badge',                    'info-fg',        'info-bg',      Contrast::AA_TEXT],
        ['Danger button label',           'neutral-0',      'danger',       Contrast::AA_TEXT],
        ['Error text on card',            'danger-fg',      'bg-surface',   Contrast::AA_TEXT],
        ['Toast text',                    'toast-fg',       'toast-bg',     Contrast::AA_TEXT],
        ['Toast success dot',             'toast-success',  'toast-bg',     Contrast::AA_LARGE_OR_UI],
        ['Toast warning dot',             'toast-warning',  'toast-bg',     Contrast::AA_LARGE_OR_UI],
        ['Toast danger dot',              'toast-danger',   'toast-bg',     Contrast::AA_LARGE_OR_UI],
        ['Toast info dot',                'toast-info',     'toast-bg',     Contrast::AA_LARGE_OR_UI],
    ];

    /**
     * Stage (projector) theme overrides.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float}>
     */
    private const array STAGE_PAIRS = [
        ['Stage text',           'text-primary',   'bg-canvas', Contrast::AA_TEXT],
        ['Stage secondary text', 'text-secondary', 'bg-canvas', Contrast::AA_TEXT],
        ['Stage tertiary text',  'text-tertiary',  'bg-canvas', Contrast::AA_TEXT],
        ['Stage gold on basalt', 'brand',          'bg-canvas', Contrast::AA_TEXT],
        ['Stage label on gold',  'brand-fg',       'brand',     Contrast::AA_TEXT],
    ];

    /**
     * Public site theme (homepage brief, direction A "Stage night").
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float}>
     */
    private const array SITE_PAIRS = [
        ['Site headline on hero',          'hero-text',         'hero-bg',     Contrast::AA_TEXT],
        ['Site supporting text on hero',   'hero-muted',        'hero-bg',     Contrast::AA_TEXT],
        ['Site gold text on hero',         'hero-accent',       'hero-bg',     Contrast::AA_TEXT],
        ['Site hero button label',         'hero-accent-fg',    'hero-accent', Contrast::AA_TEXT],
        ['Site search text',               'hero-text',         'hero-field',  Contrast::AA_TEXT],
        ['Site search placeholder',        'hero-muted',        'hero-field',  Contrast::AA_TEXT],
        ['Site muted text on raised hero', 'hero-muted',        'hero-raised', Contrast::AA_TEXT],
        ['Site search field border',       'hero-field-border', 'hero-bg',     Contrast::AA_LARGE_OR_UI],
        ['Site focus ring on hero',        'hero-focus',        'hero-bg',     Contrast::AA_LARGE_OR_UI],
        ['Site button label',              'brand-fg',          'brand',       Contrast::AA_TEXT],
        ['Site button label (hover)',      'brand-fg',          'brand-hover', Contrast::AA_TEXT],
        ['Site link text on page',         'brand-text',        'bg-canvas',   Contrast::AA_TEXT],
        ['Site link text on card',         'brand-text',        'bg-surface',  Contrast::AA_TEXT],
        ['Site status pill',               'pill-fg',           'pill-bg',     Contrast::AA_TEXT],
        ['Site focus ring on page',        'focus-ring',        'bg-canvas',   Contrast::AA_LARGE_OR_UI],
        ['Site gold accent on hero',       'gold',              'hero-bg',     Contrast::AA_LARGE_OR_UI],
    ];

    /**
     * Colour tokens of a theme block (':root' or "[data-theme='stage']"), var() references resolved.
     *
     * @return array<string, string> name (without --) => #rrggbb
     */
    public static function colors(string $selector = ':root'): array
    {
        $css = (string) file_get_contents(resource_path('css/theme.css'));
        $values = self::block($css, ':root');

        if ($selector !== ':root') {
            $values = [...$values, ...self::block($css, $selector)];
        }

        $resolved = [];

        foreach ($values as $name => $value) {
            for ($depth = 0; $depth < 5 && preg_match('/^var\(--([\w-]+)\)$/', $value, $m) === 1; $depth++) {
                $value = $values[$m[1]] ?? $value;
            }

            if (Contrast::isHex($value)) {
                $resolved[$name] = strtolower($value);
            }
        }

        return $resolved;
    }

    /**
     * @return list<array{usage: string, theme: string, foreground: string, background: string, minimum: float}>
     */
    public static function pairs(): array
    {
        $out = [];
        $themes = [
            'light' => [':root', self::LIGHT_PAIRS],
            'stage' => ["[data-theme='stage']", self::STAGE_PAIRS],
            'site' => ["[data-theme='site']", self::SITE_PAIRS],
        ];

        foreach ($themes as $theme => [$selector, $list]) {
            $colors = self::colors($selector);

            foreach ($list as [$usage, $fg, $bg, $minimum]) {
                $out[] = [
                    'usage' => $usage,
                    'theme' => $theme,
                    'foreground' => $colors[$fg] ?? '',
                    'background' => $colors[$bg] ?? '',
                    'minimum' => $minimum,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function block(string $css, string $selector): array
    {
        $start = strpos($css, $selector . ' {');

        if ($start === false) {
            return [];
        }

        $body = substr($css, $start, (int) strpos($css, '}', $start) - $start);
        preg_match_all('/--([\w-]+):\s*([^;]+);/', $body, $matches, PREG_SET_ORDER);
        $values = [];

        foreach ($matches as $match) {
            $values[$match[1]] = trim($match[2]);
        }

        return $values;
    }
}
