<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Payment\Data\PaymentVerification;
use Closure;

/**
 * In-memory stand-in for Paystack. By default every verification succeeds for exactly the
 * amount and currency that was initialized, which tests then override to model failures.
 */
final class FakePaymentGateway implements PaymentGateway
{
    // @mago-expect lint:no-literal-password
    public const string WEBHOOK_SECRET = 'fake-webhook-secret';

    /** @var list<CheckoutRequest> */
    public array $initialized = [];

    /** @var list<string> */
    public array $verified = [];

    /** @var array<string, PaymentVerification> */
    private array $verifications = [];

    private bool $initializeFails = false;

    private bool $verifyFails = false;

    /** @var (Closure(string): void)|null */
    private ?Closure $beforeVerifyReturns = null;

    public function initialize(CheckoutRequest $request): CheckoutSession
    {
        if ($this->initializeFails) {
            throw new PaymentGatewayException('Fake gateway refused the checkout.');
        }

        $this->initialized[] = $request;
        $this->verifications[$request->reference] ??= new PaymentVerification(
            reference: $request->reference,
            status: GatewayPaymentStatus::Success,
            amount: $request->amount,
            currency: $request->currency,
            providerStatus: 'success',
        );

        return new CheckoutSession($request->reference, "https://checkout.test/{$request->reference}");
    }

    public function verify(string $reference): PaymentVerification
    {
        $this->verified[] = $reference;

        if ($this->verifyFails || !array_key_exists($reference, $this->verifications)) {
            throw new PaymentGatewayException('Fake gateway could not verify.');
        }

        if ($this->beforeVerifyReturns !== null) {
            ($this->beforeVerifyReturns)($reference);
        }

        return $this->verifications[$reference];
    }

    public function hasValidWebhookSignature(string $rawBody, ?string $signature): bool
    {
        return $signature !== null && hash_equals(self::sign($rawBody), $signature);
    }

    public static function sign(string $rawBody): string
    {
        return hash_hmac('sha512', $rawBody, self::WEBHOOK_SECRET);
    }

    public function failInitialization(): self
    {
        $this->initializeFails = true;

        return $this;
    }

    public function failVerification(): self
    {
        $this->verifyFails = true;

        return $this;
    }

    /**
     * Override what the provider reports for a reference.
     */
    public function reports(
        string $reference,
        GatewayPaymentStatus $status = GatewayPaymentStatus::Success,
        ?int $amount = null,
        string $currency = 'GHS',
        ?string $reportedReference = null,
    ): self {
        $this->verifications[$reference] = new PaymentVerification(
            reference: $reportedReference ?? $reference,
            status: $status,
            amount: $amount ?? $this->verifications[$reference]->amount ?? 0,
            currency: $currency,
            providerStatus: $status->value,
        );

        return $this;
    }

    /**
     * Run a callback while the verification is "in flight", e.g. to simulate a racing worker.
     *
     * @param  Closure(string): void  $callback
     */
    public function beforeVerifyReturns(Closure $callback): self
    {
        $this->beforeVerifyReturns = $callback;

        return $this;
    }
}
