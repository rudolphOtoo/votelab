<?php

declare(strict_types=1);

use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\GatewayPaymentStatus;

beforeEach(function (): void {
    $this->gateway = fakeGateway();
    $this->transaction = VoteTransaction::factory()->create(['quantity' => 4]);
    $this->gateway->reports($this->transaction->payment_reference, amount: $this->transaction->gross_amount);
});

it('verifies, credits and confirms when the voter returns first', function (): void {
    $this
        ->get(route('payments.callback', ['reference' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertSee('Your votes are in')
        ->assertSee('4 votes for')
        ->assertSee($this->transaction->payment_reference);

    expect($this->transaction->nominee->fresh()?->votes_count)->toBe(4);
});

it('shows success without crediting again when the webhook got there first', function (): void {
    app(FulfillPaidVoteOrderAction::class)->execute($this->transaction->payment_reference, PaymentTrigger::Webhook);

    $this
        ->get(route('payments.callback', ['trxref' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertSee('Your votes are in');

    expect($this->transaction->nominee->fresh()?->votes_count)->toBe(4)->and($this->gateway->verified)->toHaveCount(1);
});

it('shows a pending page when Paystack cannot be reached', function (): void {
    $this->gateway->failVerification();

    $this
        ->get(route('payments.callback', ['reference' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertSee('Confirming your payment');

    expect($this->transaction->fresh()?->status)->toBe(TransactionStatus::Pending);
});

it('explains a failed payment', function (): void {
    $this->gateway->reports(
        $this->transaction->payment_reference,
        GatewayPaymentStatus::Failed,
        $this->transaction->gross_amount,
    );

    $this
        ->get(route('payments.callback', ['reference' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertSee('Payment not completed');
});

it('tells the voter about a paid but uncredited payment', function (): void {
    $this->transaction->awardCategory->forceFill(['status' => App\Enums\CategoryStatus::Revealed])->save();

    $this
        ->get(route('payments.callback', ['reference' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertSee('Voting closed before your payment was confirmed')
        ->assertSee('refund');
});

it('returns 404 for unknown or malformed references', function (string $reference): void {
    $this->get(route('payments.callback', ['reference' => $reference]))->assertNotFound();
})->with(['VL_NOPE', '../../etc/passwd', '']);

it('never shows the nominee\'s running vote total', function (): void {
    $this->transaction->nominee->forceFill(['votes_count' => 8_765])->save();

    $this
        ->get(route('payments.callback', ['reference' => $this->transaction->payment_reference]))
        ->assertOk()
        ->assertDontSee('8,765')
        ->assertDontSee('8765')
        ->assertDontSee('8769');
});
