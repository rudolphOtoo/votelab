<?php

declare(strict_types=1);

namespace App\Services\Security;

use SensitiveParameter;

/**
 * Turns a payer's phone number into what we may keep (D-86, D-12): a keyed hash for exact search and the
 * last three digits for masked display. The number itself is never stored.
 *
 * Numbers are normalized to Ghana's international form (233 + 9 digits) first, so "024 123 4567",
 * "+233241234567" and "233241234567" hash alike.
 */
final class PhoneHasher
{
    /**
     * @return array{phone_hash: string, phone_last3: string}|null  null when it isn't a usable number
     */
    public function digest(#[SensitiveParameter] ?string $phone): ?array
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return ['phone_hash' => $this->hash($normalized), 'phone_last3' => substr($normalized, -3)];
    }

    public function hashForSearch(#[SensitiveParameter] string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : $this->hash($normalized);
    }

    public static function normalize(#[SensitiveParameter] ?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return match (true) {
            strlen($digits) === 10 && str_starts_with($digits, '0') => '233' . substr($digits, 1),
            strlen($digits) === 12 && str_starts_with($digits, '233') => $digits,
            strlen($digits) === 9 => '233' . $digits,
            default => null,
        };
    }

    private function hash(#[SensitiveParameter] string $normalized): string
    {
        $key = config('voting.phone_hash_key');

        return hash_hmac('sha256', $normalized, is_string($key) && $key !== '' ? $key : (string) config('app.key'));
    }
}
