<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;

/**
 * Which of a nominee's text fields an edit actually changes (UpdateNomineeAction).
 */
final class NomineeEdits
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function textChanges(Nominee $nominee, array $data): array
    {
        $changes = [];

        foreach (array_intersect_key($data, array_flip([
            'name',
            'stage_name',
            'bio',
            'nominee_code',
        ])) as $field => $raw) {
            $value = self::clean($field, $raw);

            if ($value !== $nominee->getAttribute($field)) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    /**
     * Trimmed; blank optional fields become null; codes are normalized.
     */
    private static function clean(string $field, mixed $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return $field === 'name' ? '' : null;
        }

        return $field === 'nominee_code' ? NomineeCode::normalize($value) : $value;
    }
}
