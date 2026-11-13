<?php

declare(strict_types=1);

namespace App\Support\Design;

/**
 * Shared Tailwind classes for text-like controls (x-ui.input, x-ui.select, x-ui.textarea).
 */
final class ControlClasses
{
    public const string BASE = 'block w-full rounded-[var(--radius-field)] border border-strong bg-surface px-3 text-base text-ink placeholder:text-ink-tertiary transition-colors duration-[var(--duration-fast)] focus:border-focused focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-ring disabled:bg-subtle disabled:text-ink-tertiary aria-invalid:border-danger';
}
