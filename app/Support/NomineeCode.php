<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Nominee;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Short, human-friendly nominee codes (e.g. "AK27") that voters can read out or type, and that a future
 * WhatsApp/USSD channel can use as its only key. Uppercase, unique per program (also a database unique
 * key), and free of look-alike characters: no 0/O or 1/I.
 */
final class NomineeCode
{
    public const string LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const string DIGITS = '23456789';

    /**
     * A custom code: 2–8 characters from the unambiguous alphabet.
     */
    public const string PATTERN = '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{2,8}$/';

    public static function normalize(string $code): string
    {
        return Str::upper(preg_replace('/[\s-]+/', '', $code) ?? '');
    }

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    /**
     * Normalize and validate a requested code, and make sure no other nominee in the program uses it.
     *
     * @throws ValidationException
     */
    public static function ensureAvailable(string $requested, int $awardProgramId, ?int $ignoreNomineeId = null): string
    {
        $code = self::normalize($requested);

        if (!self::isValid($code)) {
            throw ValidationException::withMessages([
                'nominee_code' => 'Use 2–8 capital letters and digits, without 0, O, 1 or I.',
            ]);
        }

        $taken = Nominee::query()
            ->where('award_program_id', $awardProgramId)
            ->where('nominee_code', $code)
            ->when($ignoreNomineeId !== null, static fn($query) => $query->whereKeyNot($ignoreNomineeId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['nominee_code' => "{$code} is already used in this program."]);
        }

        return $code;
    }

    /**
     * Two letters (the name's initials when usable) and two digits, unique within the program.
     */
    public static function generate(int $awardProgramId, string $name): string
    {
        $initials = self::initials($name);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $letters = $attempt < 25 && $initials !== null ? $initials : self::random(self::LETTERS, 2);
            $code = $letters . self::random(self::DIGITS, 2);

            if (!Nominee::query()->where('award_program_id', $awardProgramId)->where('nominee_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not find a free nominee code; set one by hand.');
    }

    private static function initials(string $name): ?string
    {
        $words = preg_split('/\s+/', Str::upper(Str::ascii(trim($name))));
        $words = $words === false ? [] : $words;
        $letters = '';

        foreach ($words as $word) {
            $first = $word[0] ?? '';

            if ($first !== '' && str_contains(self::LETTERS, $first)) {
                $letters .= $first;
            }
        }

        $letters = strlen($letters) === 1 ? $letters . $letters : substr($letters, 0, 2);

        return strlen($letters) === 2 ? $letters : null;
    }

    private static function random(string $alphabet, int $length): string
    {
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
