<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * The public site's shipped dummy details (D-148: "dummy data for now"), in one place: config/site.php uses them as
 * defaults, and the production boot guard refuses to start while any is still in use (D-149). The support email is on
 * .invalid (RFC 2606), so request-access notifications can never reach a real inbox before a real address is set.
 */
final class DummySiteData
{
    public const string FEE = 'Pricing is a simple share of each paid vote. Request access and we\'ll send our rates.';

    public const string SUPPORT_EMAIL = 'support@votelab.invalid';

    public const string SUPPORT_WHATSAPP = '+233 20 000 0000';

    public const string SUPPORT_PHONE = '+233 30 000 0000';

    public const string LEGAL_NAME = '[VoteLab legal name]';

    public const string LEGAL_ADDRESS = '[Registered address], Accra';

    /** config key => the environment variable that sets it. SITE_LEGAL_DRAFT is not here: it may stay on. */
    public const array SETTINGS = [
        'site.fee' => 'SITE_FEE_WORDING',
        'site.support.email' => 'SITE_SUPPORT_EMAIL',
        'site.support.whatsapp' => 'SITE_SUPPORT_WHATSAPP',
        'site.support.phone' => 'SITE_SUPPORT_PHONE',
        'site.legal.name' => 'SITE_LEGAL_NAME',
        'site.legal.address' => 'SITE_LEGAL_ADDRESS',
    ];

    private const array DEFAULTS = [
        self::FEE,
        self::SUPPORT_EMAIL,
        self::SUPPORT_WHATSAPP,
        self::SUPPORT_PHONE,
        self::LEGAL_NAME,
        self::LEGAL_ADDRESS,
    ];

    /**
     * Whether a value is a stand-in: empty, a [bracketed] placeholder, an example or .invalid address, or a shipped
     * default.
     */
    public static function isDummy(mixed $value): bool
    {
        $value = trim(is_scalar($value) ? (string) $value : '');

        return (
            $value === ''
            || str_contains($value, '[')
            || str_contains(mb_strtolower($value), 'example')
            || str_contains(mb_strtolower($value), '.invalid')
            || in_array($value, self::DEFAULTS, true)
        );
    }

    /**
     * The environment variables whose values are still dummies.
     *
     * @return list<string>
     */
    public static function stillDummy(): array
    {
        $dummy = [];

        foreach (self::SETTINGS as $key => $variable) {
            if (self::isDummy(config($key))) {
                $dummy[] = $variable;
            }
        }

        return $dummy;
    }
}
