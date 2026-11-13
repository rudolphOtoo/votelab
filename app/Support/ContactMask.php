<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Masked contact details for lists (D-86). Full details appear only on an exact, audited lookup.
 */
final class ContactMask
{
    public static function email(?string $email): ?string
    {
        if ($email === null || !str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    /**
     * Only the last three digits are stored, so that's all a mask can show.
     */
    public static function phone(?string $last3): ?string
    {
        return $last3 === null ? null : '*******' . $last3;
    }
}
