<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A valid organization slug: lower-case words joined by single hyphens, not a reserved path
 * (config('organizations.reserved_slugs')) and not starting with "livewire".
 */
final class OrganizationSlug implements ValidationRule
{
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function isReserved(string $slug): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('organizations.reserved_slugs', []);

        return in_array($slug, $reserved, true) || str_starts_with($slug, 'livewire');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            !is_string($value)
            || strlen($value) < 2
            || strlen($value) > 64
            || preg_match(self::PATTERN, $value) !== 1
        ) {
            $fail('The :attribute must be 2–64 lower-case letters, numbers and single hyphens.');

            return;
        }

        if (self::isReserved($value)) {
            $fail('The :attribute ":input" is reserved. Choose another.');
        }
    }
}
