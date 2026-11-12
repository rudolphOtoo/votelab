<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaystackPayerPhone
{
    /**
     * The payer's mobile-money number, for D-86's phone hash. PayZephyr's verification result drops
     * Paystack's `authorization` object, so it is read from Paystack's own verify response: in test mode
     * (recorded 2026-11-12) the number is `data.authorization.mobile_money_number`, in local format
     * (0XXXXXXXXX); `data.customer.phone` is null for mobile money (D-93). Best effort: any failure returns
     * null and never affects whether the payment is credited. The number is not logged.
     */
    public function lookup(string $reference): ?string
    {
        try {
            $response = Http::baseUrl((string) config('payments.providers.paystack.base_url'))
                ->withToken((string) config('payments.providers.paystack.secret_key'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get('/transaction/verify/' . rawurlencode($reference));
        } catch (Throwable) {
            Log::channel('payments')->warning('verify.payer_phone_unavailable', ['reference' => $reference]);

            return null;
        }

        $number = $response->json('data.authorization.mobile_money_number');

        if (
            !$response->successful()
            || $response->json('data.reference') !== $reference
            || !is_string($number)
            || $number === ''
        ) {
            return null;
        }

        return $number;
    }
}
