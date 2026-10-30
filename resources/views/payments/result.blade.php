@php
    use App\Enums\TransactionStatus;
    use App\Support\Money;

    /** @var \App\Models\VoteTransaction $transaction */
    $nominee = $transaction->nominee->displayName();
    $votes = trans_choice(':count vote|:count votes', $transaction->quantity);

    [$tone, $heading, $message] = match ($transaction->status) {
        TransactionStatus::Successful => ['success', 'Your votes are in', "{$votes} for {$nominee} have been counted. Thank you for voting!"],
        TransactionStatus::Pending => ['warning', 'Confirming your payment', "We're waiting for Paystack to confirm your payment. Your {$votes} for {$nominee} will be counted as soon as it does. You can refresh this page."],
        TransactionStatus::Failed => ['danger', 'Payment not completed', "Your payment didn't go through, so no votes were counted and you were not charged."],
        TransactionStatus::PaidUncredited => ['warning', 'Voting closed before your payment was confirmed', "Your payment was received after voting closed, so your {$votes} could not be counted. The organizer will arrange a refund. Keep your reference below."],
        TransactionStatus::Flagged => ['danger', 'We could not confirm this payment', 'Something about this payment did not match our records, so no votes were counted. Contact the organizer with your reference below.'],
        TransactionStatus::Refunded => ['warning', 'Payment refunded', 'This payment was refunded, so its votes were not counted.'],
    };
@endphp

<x-layouts::app :title="$heading">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-12">
        <x-ui.card>
            <p class="text-sm text-ink-secondary">{{ $transaction->awardProgram->title }} · {{ $transaction->awardCategory->name }}</p>

            <h1 @class([
                'mt-2 text-2xl font-semibold',
                'text-success' => $tone === 'success',
                'text-warning' => $tone === 'warning',
                'text-danger' => $tone === 'danger',
            ])>{{ $heading }}</h1>

            <p class="mt-3 text-ink">{{ $message }}</p>

            <dl class="mt-6 grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-ink-secondary">Amount</dt>
                <dd class="text-right tabular-nums">{{ Money::format($transaction->gross_amount, $transaction->currency) }}</dd>
                <dt class="text-ink-secondary">Reference</dt>
                <dd class="text-right font-mono break-all">{{ $transaction->payment_reference }}</dd>
            </dl>
        </x-ui.card>
    </main>
</x-layouts::app>
