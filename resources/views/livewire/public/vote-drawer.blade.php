@php use App\Support\Money; @endphp

<div
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="vote-drawer-title"
    x-data="{ shown: false }"
    x-init="$nextTick(() => { shown = true; $refs.panel.focus() })"
    x-on:keydown.escape.window="$wire.close()"
>
    <div class="absolute inset-0 bg-ink/40 transition-opacity duration-200" :class="shown ? 'opacity-100' : 'opacity-0'" wire:click="close" aria-hidden="true"></div>

    <section
        x-ref="panel"
        tabindex="-1"
        class="vote-drawer relative max-h-[92dvh] w-full overflow-y-auto rounded-t-[var(--radius-lg)] bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl outline-none transition-[transform,opacity] duration-[260ms] ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none sm:max-w-md sm:rounded-[var(--radius-lg)]"
        :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-full opacity-0 sm:translate-y-4'"
    >
        <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-hairline sm:hidden" aria-hidden="true"></div>

        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-ink-secondary">{{ $category->name }}</p>
                <h2 id="vote-drawer-title" class="text-xl font-semibold text-ink">Vote for {{ $nominee->displayName() }}</h2>
                <p class="font-mono text-xs text-ink-tertiary">{{ $nominee->nominee_code }}</p>
            </div>
            <button type="button" wire:click="close" class="-m-2 min-h-11 min-w-11 rounded-full p-2 text-ink-secondary hover:bg-hover" aria-label="Close">
                <svg class="mx-auto size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
        </header>

        @if ($allowsFree && $allowsPaid)
            <div class="mt-5 grid grid-cols-2 gap-1 rounded-[var(--radius-md)] bg-subtle p-1" role="tablist">
                @foreach (['paid' => 'Buy votes', 'free' => 'Free vote'] as $value => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                        wire:click="showTab('{{ $value }}')"
                        @class([
                            'min-h-11 rounded-[var(--radius-sm)] text-sm font-medium transition-colors',
                            'bg-surface text-ink shadow-sm' => $tab === $value,
                            'text-ink-secondary' => $tab !== $value,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        @endif

        @if ($tab === 'free' && $allowsFree)
            <div class="mt-5">
                <p class="text-sm text-ink-secondary">
                    {{ trans_choice('You get :count free vote per day in this category.|You get :count free votes per day in this category.', $freeVoteLimit) }}
                </p>
                <button
                    type="button"
                    wire:click="castFreeVote"
                    wire:loading.attr="disabled"
                    class="mt-4 min-h-12 w-full rounded-[var(--radius-md)] bg-primary text-base font-semibold text-primary-fg transition-transform active:scale-[0.98] disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="castFreeVote">Cast my free vote</span>
                    <span wire:loading wire:target="castFreeVote">Casting your vote…</span>
                </button>
            </div>
        @elseif ($allowsPaid)
            <form wire:submit="checkout" class="mt-5">
                <label for="vote-quantity" class="text-sm font-medium text-ink">Number of votes</label>
                <div class="mt-2 flex items-center gap-3">
                    <button type="button" wire:click="adjust(-1)" class="size-12 rounded-full border border-hairline text-xl text-ink transition-transform active:scale-[0.92]" aria-label="One fewer vote">−</button>
                    <input
                        id="vote-quantity"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="{{ $maxQuantity }}"
                        wire:model.live.debounce.400ms="quantity"
                        class="h-12 w-full rounded-[var(--radius-md)] border border-hairline bg-surface text-center text-xl font-semibold tabular-nums text-ink"
                    >
                    <button type="button" wire:click="adjust(1)" class="size-12 rounded-full border border-hairline text-xl text-ink transition-transform active:scale-[0.92]" aria-label="One more vote">+</button>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($quickAdd as $votes)
                        <button type="button" wire:click="adjust({{ $votes }})" class="min-h-11 rounded-full border border-hairline px-4 text-sm font-medium text-ink transition-transform active:scale-95">+{{ $votes }}</button>
                    @endforeach
                </div>

                @error('quantity') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror

                @if ($quote)
                    <dl class="mt-5 space-y-1.5 rounded-[var(--radius-md)] bg-subtle p-4 text-sm tabular-nums">
                        <div class="flex justify-between">
                            <dt class="text-ink-secondary">{{ trans_choice(':count vote|:count votes', $quote->quantity, ['count' => number_format($quote->quantity)]) }} × {{ Money::format($quote->unitPrice) }}</dt>
                            <dd class="text-ink">{{ Money::format($quote->subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-secondary">Processing fee (Paystack)</dt>
                            <dd class="text-ink">{{ Money::format($quote->processingFee) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-hairline pt-1.5 text-base font-semibold">
                            <dt class="text-ink">Total</dt>
                            <dd class="text-ink">{{ Money::format($quote->total) }}</dd>
                        </div>
                    </dl>
                @elseif ($quoteError)
                    <p class="mt-5 rounded-[var(--radius-md)] bg-subtle p-4 text-sm text-ink">{{ $quoteError }}</p>
                @endif

                <label for="vote-email" class="mt-5 block text-sm font-medium text-ink">Email for your receipt <span class="font-normal text-ink-tertiary">(optional)</span></label>
                <input id="vote-email" type="email" autocomplete="email" wire:model="email" class="mt-2 h-12 w-full rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-ink">
                @error('email') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="checkout"
                    @disabled($quote === null)
                    class="mt-5 min-h-12 w-full rounded-[var(--radius-md)] bg-primary text-base font-semibold text-primary-fg transition-transform active:scale-[0.98] disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="checkout">
                        @if ($quote) Pay {{ Money::format($quote->total) }} securely with Paystack @else Proceed to Secure Paystack Checkout @endif
                    </span>
                    <span wire:loading wire:target="checkout">Securing ballot with Paystack…</span>
                </button>
            </form>
        @endif

        <div aria-live="polite">
            @if ($notice)
                <p @class([
                    'mt-4 rounded-[var(--radius-md)] p-3 text-sm',
                    'bg-success-bg text-success-fg' => $noticeTone === 'success',
                    'bg-warning-bg text-warning-fg' => $noticeTone === 'warning',
                    'bg-subtle text-ink' => $noticeTone === 'info',
                ])>{{ $notice }}</p>
            @endif
        </div>
    </section>
</div>
