<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Contracts\PaymentGateway;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prices a paid-vote order (QuotePaidVotesAction), records it as a pending transaction with every
 * amount and the settlement subaccount snapshotted, and starts a Paystack Hosted Checkout.
 * Fulfilment later credits the stored values only.
 */
final readonly class InitiatePaidVoteOrderAction
{
    public function __construct(
        private PaymentGateway $gateway,
        private QuotePaidVotesAction $quotes,
    ) {}

    /**
     * @throws CheckoutUnavailableException
     */
    public function execute(Nominee $nominee, int $quantity, ?string $voterEmail = null): CheckoutSession
    {
        $quote = $this->quotes->execute($nominee, $quantity);
        $email = self::normalizeEmail($voterEmail);
        $subaccountCode = (string) $nominee->organization()->value('paystack_subaccount_code');

        $transaction = VoteTransaction::query()->create([
            'organization_id' => $nominee->organization_id,
            'award_program_id' => $nominee->award_program_id,
            'award_category_id' => $nominee->award_category_id,
            'nominee_id' => $nominee->id,
            'quantity' => $quote->quantity,
            'unit_price' => $quote->unitPrice,
            'net_amount' => $quote->subtotal,
            'processing_fee_pesewas' => $quote->processingFee,
            'gross_amount' => $quote->total,
            'currency' => (string) config('voting.currency'),
            'payment_provider' => 'paystack',
            'payment_reference' => config('voting.reference_prefix') . '_' . Str::ulid()->toBase32(),
            'settlement_subaccount_code' => $subaccountCode,
            'status' => TransactionStatus::Pending,
            'voter_email' => $email,
        ]);

        $context = [
            'reference' => $transaction->payment_reference,
            'vote_transaction_id' => $transaction->id,
            'award_program_id' => $transaction->award_program_id,
            'award_category_id' => $transaction->award_category_id,
            'quantity' => $transaction->quantity,
            'gross_amount' => $transaction->gross_amount,
        ];

        try {
            $session = $this->gateway->initialize(new CheckoutRequest(
                reference: $transaction->payment_reference,
                amount: $transaction->gross_amount,
                currency: $transaction->currency,
                email: $email ?? self::proxyEmail(),
                callbackUrl: route('payments.callback'),
                subaccountCode: $subaccountCode,
                metadata: ['vote_transaction_id' => $transaction->id],
            ));
        } catch (PaymentGatewayException $e) {
            $transaction->forceFill([
                'status' => TransactionStatus::Failed,
                'status_reason' => 'checkout_not_started',
            ])->save();

            Log::channel('payments')->error('checkout.not_started', $context + ['error' => $e->getMessage()]);

            throw CheckoutUnavailableException::gatewayUnavailable($e);
        }

        Log::channel('payments')->info('checkout.initiated', $context);

        return $session;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        if ($email === '') {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || Str::length($email) > 254) {
            throw CheckoutUnavailableException::invalidEmail();
        }

        return $email;
    }

    /**
     * Paystack requires an email. A random token, never derived from voter data.
     */
    private static function proxyEmail(): string
    {
        return 'anon-' . Str::lower(Str::random(24)) . '@' . config('voting.proxy_email_domain');
    }
}
