<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Production takes real money only with live Paystack keys (sk_live_); anywhere else only test keys
 * (sk_test_) are accepted, so a staging or local copy can never charge real voters. Used by the launch
 * checklist; the Stage 3 boot guard reuses it.
 */
final class PaystackKeyMode
{
    public static function matchesEnvironment(): bool
    {
        $key = (string) config('payments.providers.paystack.secret_key');

        return app()->isProduction() ? str_starts_with($key, 'sk_live_') : str_starts_with($key, 'sk_test_');
    }

    public static function explanation(): string
    {
        return app()->isProduction()
            ? 'Production needs live Paystack keys (sk_live_…); test keys are set.'
            : 'Outside production only test Paystack keys (sk_test_…) are allowed.';
    }
}
