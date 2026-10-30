<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Payment\Data\PaymentVerification;

/**
 * Pure decision: given a transaction awaiting payment, Paystack's verification of it and its category,
 * should votes be credited? No I/O; FulfillPaidVoteOrderAction applies the result.
 */
final readonly class DecidePaymentSettlement
{
    public function execute(
        VoteTransaction $transaction,
        PaymentVerification $verification,
        AwardCategory $category,
    ): SettlementDecision {
        if ($verification->status === GatewayPaymentStatus::Pending) {
            return SettlementDecision::wait('still_pending');
        }

        if ($verification->status === GatewayPaymentStatus::Failed) {
            // An expired checkout that was never paid stays expired.
            return $transaction->status === TransactionStatus::Expired
                ? SettlementDecision::wait('expired_unpaid')
                : SettlementDecision::settle(TransactionStatus::Failed, 'payment_failed');
        }

        $mismatch = self::mismatch($transaction, $verification);

        if ($mismatch !== null) {
            return SettlementDecision::settle(TransactionStatus::Flagged, $mismatch);
        }

        if (!self::acceptsPayment($category, $transaction)) {
            return SettlementDecision::settle(TransactionStatus::PaidUncredited, "category_{$category->status->value}");
        }

        return SettlementDecision::credit();
    }

    private static function mismatch(VoteTransaction $transaction, PaymentVerification $verification): ?string
    {
        return match (true) {
            $verification->reference !== $transaction->payment_reference => 'reference_mismatch',
            $verification->amount !== $transaction->gross_amount => 'amount_mismatch',
            $verification->currency !== $transaction->currency => 'currency_mismatch',
            default => null,
        };
    }

    /**
     * Open and embargoed categories always accept. A closed one accepts a payment only if its
     * checkout started before the close and it is verified inside the close grace window.
     */
    private static function acceptsPayment(AwardCategory $category, VoteTransaction $transaction): bool
    {
        if (!$category->status->canFulfillPayments()) {
            return false;
        }

        if ($category->status !== CategoryStatus::Closed) {
            return true;
        }

        return (
            $category->closed_at !== null
            && $transaction->created_at !== null
            && $transaction->created_at->lessThanOrEqualTo($category->closed_at)
            && $category->isWithinCloseGrace()
        );
    }
}
