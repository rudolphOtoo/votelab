<div>
    <x-admin.program-nav :program="$program" current="results" />

    <div aria-live="polite">
        @if ($notice)<p role="status" class="mb-4 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $notice }}</p>@endif
        @if ($errors->any())<p role="alert" class="mb-4 rounded-[var(--radius-md)] border border-danger/40 bg-danger/5 p-3 text-sm text-danger">{{ $errors->first() }}</p>@endif
    </div>

    <nav aria-label="Results sections" class="-mx-4 mb-6 overflow-x-auto px-4">
        <ul class="flex min-w-max gap-2">
            @foreach ($tabs as $key => $label)
                <li><button type="button" wire:click="$set('tab', '{{ $key }}')" @if ($key === $current) aria-current="page" @endif class="min-h-11 rounded-full border border-hairline px-4 text-sm text-ink-secondary aria-[current=page]:border-ink aria-[current=page]:bg-ink aria-[current=page]:text-canvas">{{ $label }}</button></li>
            @endforeach
        </ul>
    </nav>

    @if ($current === 'results')
        @if ($canExport)
            <a href="{{ route('dashboard.programs.export', $program) }}" class="mb-4 inline-flex min-h-11 items-center rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Export all results (CSV)</a>
        @endif
        @foreach ($categories as $block)
            <section class="mb-6" aria-labelledby="results-{{ $block['category']->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="results-{{ $block['category']->id }}" class="mr-auto text-lg font-semibold text-ink">{{ $block['category']->name }}</h2>
                    <x-ui.status-pill :status="$block['category']->status" />
                    @if ($canExport)<a href="{{ route('dashboard.programs.export', [$program, 'category' => $block['category']->id]) }}" class="min-h-11 content-center text-sm text-ink underline underline-offset-4">CSV</a>@endif
                </div>
                @if (!$block['visible'])
                    <p class="mt-2 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink-secondary">Results are hidden. Owners and admins can see live counts.</p>
                @else
                    {{-- Cards on phones, a table from sm up. --}}
                    <ul class="mt-3 space-y-2 sm:hidden">
                        @foreach ($block['rows'] as $row)
                            <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm">
                                <p class="font-medium text-ink">{{ $row['name'] }} <span class="font-mono text-ink-secondary">{{ $row['code'] }}</span>@if ($row['archived']) <span class="text-warning">· archived</span>@endif</p>
                                <p class="mt-1 tabular-nums text-ink-secondary">Ledger {{ number_format($row['ledger']) }} · Counter {{ number_format($row['counter']) }}
                                    @if ($row['ledger'] !== $row['counter'])<strong class="text-danger"> · drift</strong>@endif</p>
                            </li>
                        @endforeach
                    </ul>
                    <table class="mt-3 hidden w-full text-left text-sm sm:table">
                        <thead class="text-ink-secondary"><tr><th scope="col" class="py-2">Nominee</th><th scope="col">Code</th><th scope="col" class="text-right">Ledger votes</th><th scope="col" class="text-right">Counter</th><th scope="col" class="text-right">Check</th></tr></thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach ($block['rows'] as $row)
                                <tr>
                                    <td class="py-2 text-ink">{{ $row['name'] }}@if ($row['archived']) <span class="text-warning">(archived)</span>@endif</td>
                                    <td class="font-mono">{{ $row['code'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['ledger']) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row['counter']) }}</td>
                                    <td class="text-right">{!! $row['ledger'] === $row['counter'] ? '<span class="text-success">OK</span>' : '<strong class="text-danger">Drift</strong>' !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    @elseif ($current === 'transactions')
        <form wire:submit="lookup" class="flex flex-col gap-2 sm:flex-row sm:items-end" novalidate>
            <div class="flex-1"><x-form.field name="search" label="Exact reference, email or phone" hint="Every lookup is recorded in the history." wire:model="search" /></div>
            <x-form.button class="sm:w-auto">Find</x-form.button>
        </form>
        @php $rows = $searched ? $found : $recent; @endphp
        <h2 class="mt-6 text-sm font-medium text-ink-secondary">{{ $searched ? count($found).' match(es)' : 'Recent (contact details masked)' }}</h2>
        <ul class="mt-2 space-y-2">
            @forelse ($rows as $row)
                <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm">
                    <p class="font-mono text-ink break-all">{{ $row['reference'] }}</p>
                    <p class="mt-1 text-ink-secondary">{{ $row['nomineeName'] }} · {{ $row['quantity'] }} votes · {{ \App\Support\Money::format($row['grossAmount']) }} · {{ $row['status'] }}</p>
                    <p class="text-ink-secondary">{{ $row['email'] ?? 'no email' }} · {{ $row['phone'] ?? 'no phone' }}</p>
                </li>
            @empty
                <li class="text-sm text-ink-secondary">Nothing found.</li>
            @endforelse
        </ul>
    @elseif ($current === 'refunds')
        <p class="text-sm text-ink-secondary">Payments received after voting closed, or for a withdrawn nominee, are not counted and must be refunded. Refund them in Paystack, then record it here. VoteLab never moves money.</p>
        <ul class="mt-4 space-y-2">
            @forelse ($queue as $transaction)
                <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm" wire:key="refund-{{ $transaction->id }}">
                    <p class="font-mono break-all text-ink">{{ $transaction->payment_reference }}</p>
                    <p class="mt-1 text-ink-secondary">{{ $transaction->nominee->displayName() }} · {{ \App\Support\Money::format($transaction->gross_amount) }} · {{ $transaction->status_reason }}</p>
                    @if ($canRecordRefunds)
                        @if ($refundingId === $transaction->id)
                            <form wire:submit="recordRefund" class="mt-3" novalidate>
                                <x-form.field name="refundReason" id="refund-reason" label="Reason / how it was refunded" wire:model="refundReason" required />
                                <x-form.field name="refundReference" id="refund-reference" label="Paystack refund reference (optional)" wire:model="refundReference" />
                                <x-form.button class="mt-4 sm:w-auto">Record refund</x-form.button>
                            </form>
                        @else
                            <button type="button" wire:click="startRefund({{ $transaction->id }})" class="mt-2 min-h-11 rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Record refund</button>
                        @endif
                    @endif
                </li>
            @empty
                <li class="text-sm text-ink-secondary">No refunds waiting.</li>
            @endforelse
        </ul>
    @elseif ($current === 'revenue')
        <dl class="grid gap-3 sm:grid-cols-3">
            @foreach ([['Credited (organizer receives)', $revenue->credited->votePrice], ['Charged to voters', $revenue->credited->gross], ['Refunds owed', $revenue->paidUncredited->gross]] as [$label, $amount])
                <div class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4"><dt class="text-sm text-ink-secondary">{{ $label }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-ink">{{ \App\Support\Money::format($amount) }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-3 text-sm text-ink-secondary">{{ number_format($revenue->credited->votes) }} paid votes in {{ number_format($revenue->credited->transactions) }} payments.</p>
    @elseif ($current === 'history')
        <div class="max-w-xs"><x-form.field name="action" label="Filter by action (e.g. category., refund.)" wire:model.live.debounce.400ms="action" /></div>
        <ul class="mt-4 space-y-2">
            @forelse ($events as $event)
                <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-sm">
                    <p class="font-medium text-ink">{{ $event->action }} <span class="font-normal text-ink-secondary">· {{ $event->created_at->timezone(config('app.timezone'))->format('j M Y, H:i') }}</span></p>
                    <p class="text-ink-secondary">By user #{{ $event->actor_user_id ?? 'system' }} · {{ $event->subject_type }} #{{ $event->subject_id }}</p>
                    @if ($event->before || $event->after)
                        <p class="mt-1 break-all font-mono text-xs text-ink-secondary">{{ json_encode($event->before) }} → {{ json_encode($event->after) }}</p>
                    @endif
                </li>
            @empty
                <li class="text-sm text-ink-secondary">No history yet.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</div>
