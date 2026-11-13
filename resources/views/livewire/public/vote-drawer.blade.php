@php use App\Support\Money; @endphp

{{--
  The money moment: a bottom sheet on phones, a side panel from 640 px. Focus is trapped while open, Esc and
  the backdrop close it, and the page behind is inert. The nominee's photo, name and code sit at the top so
  voters know they picked the right person.
--}}
<div
    class="fixed inset-0 z-[var(--z-sheet)]"
    role="dialog"
    aria-modal="true"
    aria-labelledby="vote-drawer-title"
    x-data="{ shown: false }"
    x-init="$nextTick(() => shown = true)"
    x-trap.noscroll="shown"
    x-on:keydown.escape.window="$wire.close()"
>
    <div class="absolute inset-0 bg-black/45 transition-opacity duration-[var(--duration-base)]" :class="shown ? 'opacity-100' : 'opacity-0'" wire:click="close" aria-hidden="true"></div>

    <section
        class="absolute inset-x-0 bottom-0 flex max-h-[92dvh] flex-col rounded-t-[var(--radius-lg)] bg-surface shadow-overlay outline-none transition-transform duration-[var(--duration-slow)] ease-[var(--ease-sheet)] motion-reduce:transition-none sm:inset-y-0 sm:left-auto sm:right-0 sm:max-h-none sm:w-full sm:max-w-md sm:rounded-none sm:rounded-l-[var(--radius-lg)]"
        :class="shown ? 'translate-y-0 sm:translate-x-0' : 'translate-y-full sm:translate-y-0 sm:translate-x-full'"
    >
        <div class="mx-auto mt-2 h-1.5 w-10 shrink-0 rounded-full bg-hairline sm:hidden" aria-hidden="true"></div>

        <header class="flex items-start gap-3 border-b border-hairline px-5 pb-4 pt-3 sm:pt-5">
            <div class="w-14 shrink-0">
                <x-ui.nominee-photo :src="$nominee->avatar_url" :srcset="\App\Support\NomineePortrait::srcset($nominee)" sizes="56px" :name="$nominee->displayName()" alt="" :width="56" :eager="true" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-ink-secondary">{{ $category->name }}</p>
                <h2 id="vote-drawer-title" class="text-lg font-semibold leading-snug text-ink">Vote for {{ $nominee->displayName() }}</h2>
                <p class="mt-1 inline-flex rounded-[var(--radius-sm)] bg-subtle px-2 py-0.5 font-mono text-sm font-semibold tracking-wide text-ink">
                    <span class="sr-only">Code </span>{{ $nominee->nominee_code }}
                </p>
            </div>
            <button type="button" wire:click="close" class="-mr-2 -mt-1 inline-flex size-11 shrink-0 items-center justify-center rounded-full text-ink-secondary hover:bg-hover" aria-label="Close">
                <x-ui.icon name="x" />
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            @if ($allowsFree && $allowsPaid)
                <div class="mb-4 grid grid-cols-2 gap-1 rounded-[var(--radius-md)] bg-subtle p-1" role="group" aria-label="How do you want to vote?">
                    @foreach (['paid' => 'Buy votes', 'free' => 'Free vote'] as $value => $label)
                        <button
                            type="button"
                            aria-pressed="{{ $tab === $value ? 'true' : 'false' }}"
                            wire:click="showTab('{{ $value }}')"
                            @class([
                                'min-h-11 rounded-[var(--radius-sm)] text-sm font-semibold transition-colors',
                                'bg-surface text-ink shadow-card' => $tab === $value,
                                'text-ink-secondary hover:text-ink' => $tab !== $value,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            @endif

            @if ($tab === 'free' && $allowsFree)
                <div class="rounded-[var(--radius-md)] bg-primary-subtle p-4">
                    <p class="font-semibold text-ink">
                        @if ($freeVotesLeft > 0)
                            {{ trans_choice('You have :count free vote left today in this category.|You have :count free votes left today in this category.', $freeVotesLeft) }}
                        @else
                            You've used today's free {{ trans_choice('vote|votes', $freeVoteLimit) }} in this category.
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-ink-secondary">
                        {{ trans_choice('Everyone gets :count free vote per day.|Everyone gets :count free votes per day.', $freeVoteLimit) }}
                        @if ($freeVotesLeft === 0) Come back tomorrow{{ $allowsPaid ? ', or buy votes' : '' }}. @endif
                    </p>
                </div>
                <x-ui.button type="button" size="lg" block class="mt-4" wire:click="castFreeVote" loading="castFreeVote" :disabled="$freeVotesLeft === 0">
                    <span wire:loading.remove wire:target="castFreeVote">Cast my free vote</span>
                    <span wire:loading wire:target="castFreeVote">Casting your vote…</span>
                </x-ui.button>
            @elseif ($allowsPaid)
                <form id="vote-form" wire:submit="checkout">
                    <p class="text-sm text-ink-secondary"><span class="font-semibold tabular-nums text-ink">{{ Money::format($category->price_per_vote) }}</span> per vote</p>

                    <label for="vote-quantity" class="mt-4 block text-sm font-medium text-ink">Number of votes</label>
                    <div class="mt-2 flex items-stretch gap-2">
                        <button type="button" wire:click="adjust(-1)" @disabled($quantity <= 1) class="inline-flex size-12 shrink-0 items-center justify-center rounded-[var(--radius-md)] border border-strong bg-surface text-ink transition-transform active:scale-[0.92] disabled:opacity-50" aria-label="One fewer vote"><x-ui.icon name="minus" /></button>
                        <input
                            id="vote-quantity"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="{{ $maxQuantity }}"
                            wire:model.live.debounce.400ms="quantity"
                            @error('quantity') aria-invalid="true" aria-describedby="vote-quantity-error" @enderror
                            class="min-h-12 w-full min-w-0 rounded-[var(--radius-field)] border border-strong bg-surface text-center text-xl font-semibold tabular-nums text-ink focus:border-focused focus:outline-none"
                        >
                        <button type="button" wire:click="adjust(1)" @disabled($quantity >= $maxQuantity) class="inline-flex size-12 shrink-0 items-center justify-center rounded-[var(--radius-md)] border border-strong bg-surface text-ink transition-transform active:scale-[0.92] disabled:opacity-50" aria-label="One more vote"><x-ui.icon name="plus" /></button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Quick picks">
                        @foreach ($bundles as $votes)
                            <button
                                type="button"
                                wire:click="$set('quantity', {{ $votes }})"
                                aria-pressed="{{ $quantity === $votes ? 'true' : 'false' }}"
                                @class([
                                    'min-h-11 min-w-12 rounded-full border px-4 text-sm font-semibold tabular-nums transition-transform active:scale-95',
                                    'border-primary bg-primary-subtle text-primary-text' => $quantity === $votes,
                                    'border-strong bg-surface text-ink hover:bg-hover' => $quantity !== $votes,
                                ])
                            >{{ $votes }}</button>
                        @endforeach
                    </div>

                    @error('quantity') <p id="vote-quantity-error" class="mt-2 text-sm font-medium text-danger-fg">{{ $message }}</p> @enderror

                    @if ($quote)
                        <dl class="mt-5 space-y-1.5 rounded-[var(--radius-md)] bg-subtle p-4 text-sm tabular-nums transition-opacity" wire:loading.class="opacity-60" wire:target="quantity,adjust">
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-secondary">{{ trans_choice(':count vote|:count votes', $quote->quantity, ['count' => number_format($quote->quantity)]) }} × {{ Money::format($quote->unitPrice) }}</dt>
                                <dd class="text-ink">{{ Money::format($quote->subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-secondary">Processing fee (Paystack)</dt>
                                <dd class="text-ink">{{ Money::format($quote->processingFee) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-t border-hairline pt-2 text-base font-semibold">
                                <dt class="text-ink">Total</dt>
                                <dd class="text-ink">{{ Money::format($quote->total) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-ink-secondary">The organizer receives the full vote price; the processing fee covers Paystack's charge.</p>
                    @elseif ($quoteError)
                        <p class="mt-5 rounded-[var(--radius-md)] bg-warning-bg p-4 text-sm text-warning-fg">{{ $quoteError }}</p>
                    @endif

                    <div class="mt-5">
                        <x-ui.field name="email" id="vote-email" type="email" label="Email for a payment receipt" optional
                            hint="Paystack may email you a receipt for this payment. Your payment reference is shown on the next page either way."
                            autocomplete="email" inputmode="email" wire:model="email" />
                    </div>
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
        </div>

        @if ($tab !== 'free' && $allowsPaid)
            <footer class="border-t border-hairline px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3">
                <x-ui.button type="submit" form="vote-form" size="lg" block loading="checkout" :disabled="$quote === null">
                    <span wire:loading.remove wire:target="checkout">
                        @if ($quote) Pay {{ Money::format($quote->total) }} securely with Paystack @else Proceed to Secure Paystack Checkout @endif
                    </span>
                    <span wire:loading wire:target="checkout">Securing ballot with Paystack…</span>
                </x-ui.button>
                <p class="mt-2 flex items-center justify-center gap-1.5 text-center text-xs text-ink-secondary">
                    <x-ui.icon name="lock" class="size-3.5" />
                    Mobile money (MTN, Telecel, AirtelTigo) or card
                </p>
            </footer>
        @endif
    </section>
</div>
