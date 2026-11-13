<?php

declare(strict_types=1);

namespace App\Support\Design;

/**
 * The --brand-* tokens for a page: the fill colour, its hover, the text colour on it, a version safe as text on
 * the page background, and a light tint. $custom is false when the VoteLab brand is used.
 */
final readonly class BrandPalette
{
    public function __construct(
        public string $brand,
        public string $hover,
        public string $foreground,
        public string $text,
        public string $subtle,
        public bool $custom,
    ) {}

    /**
     * CSS custom properties overriding the theme's brand tokens (for a style attribute or a :root block).
     */
    public function declarations(): string
    {
        return (
            "--brand: {$this->brand}; --brand-hover: {$this->hover}; --brand-fg: {$this->foreground}; "
            . "--brand-text: {$this->text}; --brand-subtle: {$this->subtle};"
        );
    }
}
