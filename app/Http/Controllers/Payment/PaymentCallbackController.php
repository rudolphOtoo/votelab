<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\PaymentTrigger;
use App\Exceptions\PaymentGatewayException;
use App\Models\VoteTransaction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where Paystack sends the voter after checkout. Verifies and credits through the same action as
 * the webhook, so it shows the right outcome whichever of the two arrives first.
 */
final class PaymentCallbackController
{
    public function __invoke(Request $request, FulfillPaidVoteOrderAction $fulfil): View
    {
        $reference = $request->query('reference', $request->query('trxref'));

        abort_unless(is_string($reference) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $reference) === 1, 404);

        try {
            $transaction = $fulfil->execute($reference, PaymentTrigger::Callback)->transaction;
        } catch (PaymentGatewayException|LockTimeoutException) {
            // The webhook will settle it; show the transaction as it stands.
            $transaction = VoteTransaction::query()->where('payment_reference', $reference)->first();
        }

        abort_if($transaction === null, 404);

        $transaction->loadMissing(['nominee', 'awardCategory', 'awardProgram']);

        return view('payments.result', ['transaction' => $transaction]);
    }
}
