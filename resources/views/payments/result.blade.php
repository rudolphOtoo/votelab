@php
    use App\Enums\TransactionStatus;
    use App\Support\Money;

    /** @var \App\Models\VoteTransaction $transaction */
    $nominee = $transaction->nominee;
    $name = $nominee->displayName();
    $program = $transaction->awardProgram;
    $organization = $program->organization;
    $votes = trans_choice(':count vote|:count votes', $transaction->quantity, ['count' => number_format($transaction->quantity)]);
    $nomineeUrl = route('public.nominee', [$organization, $program, $nominee->nominee_code]);

    // [tone, icon, heading, message]. Tone picks the icon colours; every state keeps its own heading.
    [$tone, $icon, $heading, $message] = match ($transaction->status) {
        TransactionStatus::Successful => ['success', 'success', 'Your votes are in', "{$votes} for {$name} have been counted. Thank you for voting!"],
        TransactionStatus::Pending => ['warning', 'clock', 'Confirming your payment', "Waiting for mobile money approval — check your phone and approve the prompt. Your {$votes} for {$name} will be counted as soon as Paystack confirms the payment."],
        TransactionStatus::Failed => ['danger', 'x', 'Payment not completed', "Your payment didn't go through, so no votes were counted and you were not charged. You can try again."],
        TransactionStatus::PaidUncredited => ['warning', 'alert', 'Voting closed before your payment was confirmed', "Your payment was received after voting closed, so your {$votes} could not be counted. The organizer will arrange a refund. Keep your reference below."],
        TransactionStatus::Flagged => ['danger', 'alert', 'We could not confirm this payment', 'Something about this payment did not match our records, so no votes were counted. Contact the organizer with your reference below.'],
        TransactionStatus::Refunded => ['warning', 'info', 'Payment refunded', 'This payment was refunded, so its votes were not counted.'],
        TransactionStatus::Expired => ['warning', 'clock', 'Checkout expired', "We didn't receive a payment for this checkout, so no votes were counted. If you were charged, your {$votes} will be counted as soon as Paystack confirms it."],
    };

    $shareText = "I just voted for {$name} in {$transaction->awardCategory->name} at {$program->title}! Vote with code {$nominee->nominee_code}: {$nomineeUrl}";
@endphp

<x-layouts::app :title="$heading" robots="noindex" bundle="result" :brand-organization="$organization"
    :description="'Your vote payment for '.$name.' at '.$program->title.'.'">
    <x-public.site-header :organization="$organization" :program="$program" />

    <main id="main" class="mx-auto max-w-lg px-4 py-8 sm:py-12">
        <div class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card sm:p-6" data-payment-state="{{ $transaction->status->value }}">
            <span @class([
                'flex size-12 items-center justify-center rounded-full',
                'bg-success-bg text-success-fg' => $tone === 'success',
                'bg-warning-bg text-warning-fg' => $tone === 'warning',
                'bg-danger-bg text-danger-fg' => $tone === 'danger',
            ])><x-ui.icon :name="$icon" class="size-6" /></span>

            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-ink">{{ $heading }}</h1>
            <p class="mt-2 text-ink-secondary">{{ $message }}</p>

            @if ($transaction->status === TransactionStatus::Pending)
                {{-- Re-checks with Paystack by reloading (each load verifies, rate-limited per device). Pausable.
                     Plain JavaScript (resources/js/result.js); without it, "Check now" and the noscript line remain. --}}
                <div class="mt-5 rounded-[var(--radius-md)] bg-subtle p-4" data-recheck="10">
                    <p class="text-sm text-ink">
                        <span data-recheck-running hidden>Checking again in <span class="tabular-nums" data-recheck-seconds>10</span> s.</span>
                        <span data-recheck-paused hidden>Automatic checks paused.</span>
                        <noscript>Refresh this page to check again.</noscript>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button :href="request()->fullUrl()" size="sm">Check now</x-ui.button>
                        <x-ui.button type="button" variant="secondary" size="sm" data-recheck-toggle hidden>Pause checks</x-ui.button>
                    </div>
                </div>
            @endif

            <dl class="mt-6 divide-y divide-hairline border-y border-hairline text-sm">
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="shrink-0 text-ink-secondary">Nominee</dt>
                    <dd class="text-right font-medium text-ink">{{ $name }} <span class="font-mono text-ink-secondary">({{ $nominee->nominee_code }})</span></dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="shrink-0 text-ink-secondary">Category</dt>
                    <dd class="text-right text-ink">{{ $transaction->awardCategory->name }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="shrink-0 text-ink-secondary">Votes</dt>
                    <dd class="text-right tabular-nums text-ink">{{ number_format($transaction->quantity) }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="shrink-0 text-ink-secondary">Amount</dt>
                    <dd class="text-right tabular-nums text-ink">{{ Money::format($transaction->gross_amount, $transaction->currency) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                    <dt class="shrink-0 text-ink-secondary">Reference</dt>
                    <dd class="flex min-w-0 items-center gap-1">
                        <span class="break-all font-mono text-ink">{{ $transaction->payment_reference }}</span>
                        {{-- Same look as x-ui.copy, without Alpine (resources/js/result.js). shrink-0: the reference wraps,
                             never the button's label (it read "Cop / y" at 360 px). --}}
                        <span class="inline-flex shrink-0">
                            <x-ui.button type="button" variant="ghost" size="sm" class="group" aria-label="Copy reference"
                                :data-copy="$transaction->payment_reference" data-copied-label="Copied">
                                <x-ui.icon name="copy" class="size-4 group-data-copied:hidden" /><x-ui.icon name="check" class="hidden size-4 group-data-copied:inline" />
                                <span data-copy-label>Copy</span>
                            </x-ui.button>
                            <span class="sr-only" aria-live="polite" data-copy-status></span>
                        </span>
                    </dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-col gap-2">
                @if ($transaction->status === TransactionStatus::Successful)
                    <x-ui.button :href="'https://wa.me/?text='.rawurlencode($shareText)" size="lg" block target="_blank" rel="noopener">
                        <x-ui.icon name="share" class="size-5" /> Share that you voted
                    </x-ui.button>
                    <x-ui.button :href="$nomineeUrl" variant="secondary" size="lg" block>Vote again</x-ui.button>
                @elseif (in_array($transaction->status, [TransactionStatus::Failed, TransactionStatus::Expired], true))
                    <x-ui.button :href="$nomineeUrl" size="lg" block>Try again</x-ui.button>
                @endif
                <x-ui.button :href="route('public.program', [$organization, $program])" variant="ghost" block>Back to {{ $program->title }}</x-ui.button>
            </div>
        </div>

        <p class="mt-4 text-center text-sm text-ink-secondary">
            Questions about this payment? Contact {{ $organization->name }} and quote your reference.
        </p>
    </main>

    <x-public.site-footer />
</x-layouts::app>
