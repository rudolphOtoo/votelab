<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Contracts\PaymentGateway;
use App\Jobs\FulfillPaidVoteOrderJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Paystack webhooks. The signature is checked against the raw body before anything
 * else; the payload is then only used to learn which reference to verify. Nothing in it is
 * trusted: the queued job asks Paystack for the payment's real state.
 */
final class PaystackWebhookController
{
    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        $rawBody = $request->getContent();

        if (!$gateway->hasValidWebhookSignature($rawBody, $request->header('x-paystack-signature'))) {
            Log::channel('payments')->warning('webhook.invalid_signature', ['ip' => $request->ip()]);

            return response('', 401);
        }

        $payload = json_decode($rawBody, true);
        $event = is_array($payload) && is_string($payload['event'] ?? null) ? $payload['event'] : null;
        $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = is_string($data['reference'] ?? null) ? $data['reference'] : null;

        Log::channel('payments')->info('webhook.received', ['event' => $event, 'reference' => $reference]);

        if ($event === 'charge.success' && $reference !== null && $reference !== '') {
            FulfillPaidVoteOrderJob::dispatch($reference);
        }

        return response('', 200);
    }
}
