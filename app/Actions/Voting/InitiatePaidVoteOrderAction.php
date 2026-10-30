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
use App\Services\Payment\PaystackFeeCalculator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prices a paid-vote order, records it as a pending transaction with every amount snapshotted,
 * and starts a Paystack Hosted Checkout. Fulfilment later credits the stored values only.
 */
final readonly class InitiatePaidVoteOrderAction
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaystackFeeCalculator $fees,
    ) {}

    /**
     * @throws CheckoutUnavailableException
     */
    public function execute(Nominee $nominee, int $quantity, ?string $voterEmail = null): CheckoutSession
    {
        $category = $nominee->awardCategory()->firstOrFail();
        $program = $category->awardProgram()->firstOrFail();

        if (!$program->status->isPublic() || !$category->status->isVotingAllowed()) {
            throw CheckoutUnavailableException::votingNotOpen();
        }

        if (!$category->voting_mode->allowsPaidVotes()) {
            throw CheckoutUnavailableException::paidVotesNotAccepted();
        }

        $maxQuantity = (int) config('voting.max_votes_per_order');

        if ($quantity < 1 || $quantity > $maxQuantity) {
            throw CheckoutUnavailableException::invalidQuantity($maxQuantity);
        }

        $email = self::normalizeEmail($voterEmail);
        $quote = $this->fees->quote($quantity, $category->price_per_vote);

        $transaction = VoteTransaction::query()->create([
            'organization_id' => $nominee->organization_id,
            'award_program_id' => $nominee->award_program_id,
            'award_category_id' => $nominee->award_category_id,
            'nominee_id' => $nominee->id,
            'quantity' => $quote->quantity,
            'unit_price' => $quote->unitPrice,
            'gross_amount' => $quote->grossAmount,
            'fee_amount' => $quote->feeAmount,
            'net_amount' => $quote->netAmount,
            'currency' => (string) config('voting.currency'),
            'payment_provider' => 'paystack',
            'payment_reference' => config('voting.reference_prefix') . '_' . Str::ulid()->toBase32(),
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
