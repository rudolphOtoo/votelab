<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Enums\TransactionStatus;
use App\Models\VoteTransaction;
use Illuminate\Http\Response;

/**
 * LOCAL ONLY (404 in every other environment): shows the payment result page for the latest transaction in a
 * given status, e.g. /dev/payments/pending, so each state can be looked at without a Paystack checkout. Read
 * only: unlike the real callback (PaymentCallbackController) it never verifies with Paystack and never
 * changes the transaction.
 */
final class PaymentResultPreviewController
{
    public function __invoke(string $status): Response
    {
        abort_unless(app()->environment('local'), 404);

        $state = TransactionStatus::tryFrom($status);
        abort_if($state === null, 404);

        $transaction = VoteTransaction::query()
            ->where('status', $state)
            ->with(['nominee', 'awardCategory', 'awardProgram.organization'])
            ->latest('id')
            ->first();

        abort_if($transaction === null, 404);

        return response()
            ->view('payments.result', ['transaction' => $transaction])
            ->header('Referrer-Policy', 'no-referrer');
    }
}
