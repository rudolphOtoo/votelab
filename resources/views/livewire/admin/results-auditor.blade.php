@php
    use App\Support\Money;
    use App\Support\PublicSchedule;
@endphp

<div>
    <x-admin.program-nav :program="$program" current="results" />

    <x-admin.notice :message="$notice" />
    @if ($errors->any())<x-admin.notice :message="$errors->first()" tone="danger" />@endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Results sections" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <ul class="flex min-w-max gap-1 rounded-[var(--radius-md)] bg-subtle p-1">
                @foreach ($tabs as $key => $label)
                    <li>
                        <button type="button" wire:click="$set('tab', '{{ $key }}')" @if ($key === $current) aria-current="page" @endif
                            class="min-h-11 rounded-[var(--radius-sm)] px-4 text-sm font-semibold text-ink-secondary hover:text-ink aria-[current=page]:bg-surface aria-[current=page]:text-ink aria-[current=page]:shadow-card">{{ $label }}</button>
                    </li>
                @endforeach
            </ul>
        </nav>
        @if ($current === 'results' && $canExport)
            <x-ui.button :href="route('dashboard.programs.export', $program)" variant="secondary" size="sm"><x-ui.icon name="download" class="size-4" /> Export all results (CSV)</x-ui.button>
        @endif
    </div>

    {{-- Shown while a tab loads. --}}
    <div wire:loading.block wire:target="tab" class="space-y-3" aria-hidden="true">
        <x-ui.skeleton shape="block" class="h-24" />
        <x-ui.skeleton shape="block" class="h-24" />
        <x-ui.skeleton :lines="3" />
    </div>

    <div wire:loading.remove wire:target="tab">
        @if ($current === 'results')
            @foreach ($categories as $block)
                <section class="mb-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card sm:p-5" aria-labelledby="results-{{ $block['category']->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="results-{{ $block['category']->id }}" class="mr-auto text-lg font-semibold text-ink">{{ $block['category']->name }}</h2>
                        <x-ui.status-pill :status="$block['category']->status" />
                        @if ($canExport)
                            <x-ui.button :href="route('dashboard.programs.export', [$program, 'category' => $block['category']->id])" variant="ghost" size="sm" aria-label="Download {{ $block['category']->name }} results (CSV)"><x-ui.icon name="download" class="size-4" /> CSV</x-ui.button>
                        @endif
                    </div>
                    @if (!$block['visible'])
                        <p class="mt-3 flex items-start gap-2 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink-secondary"><x-ui.icon name="lock" class="mt-0.5 size-4" /> Results are hidden. Owners and admins can see live counts.</p>
                    @elseif ($block['rows'] === [])
                        <p class="mt-3 text-sm text-ink-secondary">No nominees in this category yet.</p>
                    @else
                        {{-- Cards on phones, a table from 640 px. Ledger = vote_records (the source of truth); Counter = the fast total. --}}
                        <ul class="mt-3 space-y-2 sm:hidden">
                            @foreach ($block['rows'] as $row)
                                <li class="rounded-[var(--radius-md)] border border-hairline p-3 text-sm">
                                    <p class="flex flex-wrap items-center gap-2 font-medium text-ink">{{ $row['name'] }} <span class="font-mono text-ink-secondary">{{ $row['code'] }}</span>@if ($row['archived'])<x-ui.badge tone="warning">Archived</x-ui.badge>@endif</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-2 tabular-nums text-ink-secondary">
                                        Ledger {{ number_format($row['ledger']) }} · Counter {{ number_format($row['counter']) }}
                                        @if ($row['ledger'] !== $row['counter'])<x-ui.badge tone="danger" icon="alert">Drift</x-ui.badge>@endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 hidden overflow-x-auto sm:block">
                            <table class="w-full text-left text-sm">
                                <caption class="sr-only">Votes for {{ $block['category']->name }}</caption>
                                <thead class="border-b border-hairline text-ink-secondary"><tr><th scope="col" class="py-2 pr-3 font-medium">Nominee</th><th scope="col" class="pr-3 font-medium">Code</th><th scope="col" class="pr-3 text-right font-medium">Ledger votes</th><th scope="col" class="pr-3 text-right font-medium">Counter</th><th scope="col" class="text-right font-medium">Check</th></tr></thead>
                                <tbody class="divide-y divide-hairline">
                                    @foreach ($block['rows'] as $row)
                                        <tr>
                                            <td class="py-2.5 pr-3 text-ink">{{ $row['name'] }} @if ($row['archived'])<x-ui.badge tone="warning">Archived</x-ui.badge>@endif</td>
                                            <td class="pr-3 font-mono">{{ $row['code'] }}</td>
                                            <td class="pr-3 text-right tabular-nums">{{ number_format($row['ledger']) }}</td>
                                            <td class="pr-3 text-right tabular-nums">{{ number_format($row['counter']) }}</td>
                                            <td class="text-right">@if ($row['ledger'] === $row['counter'])<x-ui.badge tone="success">OK</x-ui.badge>@else<x-ui.badge tone="danger" icon="alert">Drift</x-ui.badge>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
            @if ($categories === [])
                <x-ui.empty-state title="No results yet">Results appear here once the program has categories and votes.</x-ui.empty-state>
            @endif
        @elseif ($current === 'transactions')
            <form wire:submit="lookup" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card sm:p-5" novalidate>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1"><x-form.field name="search" label="Find a payment by exact reference, email or phone" hint="Every lookup is recorded in the history." wire:model="search" /></div>
                    <x-ui.button type="submit" loading="lookup" class="sm:mb-0"><x-ui.icon name="search" class="size-5" /> Find</x-ui.button>
                </div>
            </form>
            @php $rows = $searched ? $found : $recent; @endphp
            <h2 class="mt-6 text-sm font-semibold text-ink-secondary">{{ $searched ? count($found).' match(es)' : 'Recent payments (contact details masked)' }}</h2>
            @if ($rows === [])
                <x-ui.empty-state :title="$searched ? 'No payment matches' : 'No payments yet'" class="mt-3">
                    {{ $searched ? 'Check the reference, or try the email or phone number the voter used.' : 'Payments appear here as soon as voters check out.' }}
                </x-ui.empty-state>
            @else
                <ul class="mt-2 space-y-2">
                    @foreach ($rows as $row)
                        <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="break-all font-mono text-ink">{{ $row['reference'] }}</p>
                                <x-ui.badge :tone="match ($row['status']) { 'successful' => 'success', 'pending' => 'warning', 'failed', 'flagged' => 'danger', default => 'neutral' }">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-ink-secondary">{{ $row['nomineeName'] }} · {{ number_format($row['quantity']) }} votes · <span class="tabular-nums">{{ Money::format($row['grossAmount']) }}</span></p>
                            <p class="text-ink-secondary">{{ $row['email'] ?? 'no email' }} · {{ $row['phone'] ?? 'no phone' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        @elseif ($current === 'refunds')
            <p class="flex items-start gap-2 rounded-[var(--radius-md)] bg-info-bg p-3 text-sm text-info-fg"><x-ui.icon name="info" class="mt-0.5 size-4" /> Payments received after voting closed, or for a withdrawn nominee, are not counted and must be refunded. Refund them in Paystack, then record it here. VoteLab never moves money.</p>
            @if ($queue->isEmpty())
                <x-ui.empty-state title="No refunds waiting" icon="success" class="mt-4">Nothing to refund. Payments that need one will appear here.</x-ui.empty-state>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach ($queue as $transaction)
                        <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-4 text-sm shadow-card" wire:key="refund-{{ $transaction->id }}">
                            <p class="break-all font-mono text-ink">{{ $transaction->payment_reference }}</p>
                            <p class="mt-1 text-ink-secondary">{{ $transaction->nominee->displayName() }} · <span class="tabular-nums">{{ Money::format($transaction->gross_amount) }}</span> · {{ $transaction->status_reason }}</p>
                            @if ($canRecordRefunds)
                                @if ($refundingId === $transaction->id)
                                    <div class="mt-3">
                                        <x-form.field name="refundReason" id="refund-reason" label="Reason / how it was refunded" wire:model="refundReason" required />
                                        <x-form.field name="refundReference" id="refund-reference" label="Paystack refund reference (optional)" wire:model="refundReference" />
                                        <div class="mt-4">
                                            <x-admin.confirm-sheet title="Record this refund?" confirm="Record refund" action="recordRefund" danger>
                                                Record refund
                                                <x-slot:body>You confirm that {{ Money::format($transaction->gross_amount) }} was refunded to the voter in Paystack. The record is permanent and audited; you'll be asked for your password if you haven't confirmed it recently.</x-slot:body>
                                            </x-admin.confirm-sheet>
                                        </div>
                                    </div>
                                @else
                                    <x-ui.button type="button" variant="secondary" size="sm" class="mt-3" wire:click="startRefund({{ $transaction->id }})">Record refund</x-ui.button>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @elseif ($current === 'revenue')
            <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.stat label="Credited (you receive)" :value="Money::format($revenue->credited->votePrice)" :hint="number_format($revenue->credited->votes).' paid votes'" />
                <x-ui.stat label="Charged to voters" :value="Money::format($revenue->credited->gross)" :hint="'Includes '.Money::format($revenue->credited->processingFees).' processing fees'" />
                <x-ui.stat label="Refunds owed" :value="Money::format($revenue->paidUncredited->gross)" :tone="$revenue->paidUncredited->gross > 0 ? 'danger' : null" />
            </div>
            <p class="mt-3 text-sm text-ink-secondary">{{ number_format($revenue->credited->votes) }} paid votes in {{ number_format($revenue->credited->transactions) }} payments.</p>
        @elseif ($current === 'history')
            <div class="max-w-sm"><x-form.field name="action" label="Filter by action" hint="For example category. or refund." wire:model.live.debounce.400ms="action" /></div>
            @if ($events->isEmpty())
                <x-ui.empty-state title="No history yet" class="mt-4">Changes to categories, nominees, payments and settings are recorded here.</x-ui.empty-state>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach ($events as $event)
                        <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm">
                            <p class="font-medium text-ink">{{ $event->action }} <span class="font-normal text-ink-secondary">· {{ $event->created_at->timezone(PublicSchedule::TIMEZONE)->format('j M Y, H:i') }}</span></p>
                            <p class="text-ink-secondary">By user #{{ $event->actor_user_id ?? 'system' }} · {{ $event->subject_type }} #{{ $event->subject_id }}</p>
                            @if ($event->before || $event->after)
                                <p class="mt-1 break-all font-mono text-xs text-ink-secondary">{{ json_encode($event->before) }} → {{ json_encode($event->after) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">{{ $events->links() }}</div>
            @endif
        @endif
    </div>
</div>
