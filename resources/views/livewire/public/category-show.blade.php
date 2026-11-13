@php
    use App\Enums\CategoryStatus;
    use App\Support\Money;
@endphp

<div>
    <x-public.site-header :organization="$organization" :program="$program" />

    <main
        id="main"
        class="mx-auto max-w-5xl px-4 pb-24"
        @if ($standings !== null) wire:poll.3s.visible @else wire:poll.15s.visible @endif
        x-data="{ opener: null }"
        x-on:vote-drawer-closed.window="setTimeout(() => opener && document.getElementById(opener)?.focus(), 50)"
    >
        <x-ui.breadcrumb class="pt-4" :items="[$program->title => route('public.program', [$organization, $program]), $category->name => null]" />

        <header class="pb-6 pt-5">
            <x-ui.status-pill :status="$category->status" />
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-4xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-2 max-w-2xl text-ink-secondary">{{ $category->description }}</p>
            @endif

            <p class="mt-3 text-sm text-ink-secondary">
                @if ($category->voting_mode->allowsPaidVotes())
                    <span class="font-semibold tabular-nums text-ink">{{ Money::format($category->price_per_vote) }}</span> per vote
                @endif
                @if ($category->voting_mode->allowsPaidVotes() && $category->voting_mode->allowsFreeVotes()) · @endif
                @if ($category->voting_mode->allowsFreeVotes())
                    {{ trans_choice(':count free vote per day|:count free votes per day', $category->free_vote_limit) }}
                @endif
            </p>

            @if ($standings === null && $category->status !== CategoryStatus::Draft)
                <p class="mt-4 flex items-start gap-2 rounded-[var(--radius-md)] bg-subtle px-3 py-2.5 text-sm text-ink">
                    <x-ui.icon name="lock" class="mt-0.5 size-4" />
                    <span>
                        @if ($category->status === CategoryStatus::Closed)
                            Voting has closed. Results are hidden until the reveal.
                        @else
                            Results are hidden until the reveal. Your votes still count.
                        @endif
                    </span>
                </p>
            @elseif ($standings !== null && $category->status === CategoryStatus::Open)
                <p class="mt-4 flex items-center gap-2 text-sm text-ink-secondary">
                    <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>
                    Live results, updated every few seconds.
                </p>
            @endif
        </header>

        <h2 class="sr-only">Nominees</h2>

        @if ($nominees->isEmpty())
            <x-ui.empty-state title="No nominees yet">Nominees will appear here soon.</x-ui.empty-state>
        @else
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($nominees as $nominee)
                    @php
                        $votes = $standings[$nominee->id] ?? null;
                        $isWinner = $winnerVotes !== null && $votes === $winnerVotes;
                    @endphp
                    <li wire:key="nominee-{{ $nominee->id }}" @class([
                        'flex flex-col overflow-hidden rounded-[var(--radius-lg)] border bg-surface shadow-card',
                        'border-primary ring-2 ring-primary' => $isWinner,
                        'border-hairline' => !$isWinner,
                    ])>
                        <div class="relative">
                            <x-ui.nominee-photo :src="$nominee->avatar_url" :srcset="\App\Support\NomineePortrait::srcset($nominee)" :name="$nominee->displayName()" alt="" :eager="$loop->first" class="rounded-none" />
                            @if ($isWinner)
                                <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-xs font-semibold text-primary-fg shadow-raised"><x-ui.icon name="success" class="size-3.5" /> Winner</span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <h3 class="font-semibold leading-snug text-ink">{{ $nominee->displayName() }}</h3>

                            <x-ui.copy :value="$nominee->nominee_code" :label="$nominee->nominee_code" copied-label="Copied" variant="ghost" size="sm"
                                aria-label="Copy code {{ $nominee->nominee_code }}"
                                class="-ml-2 self-start font-mono tracking-wide" />

                            @if ($votes !== null)
                                @php $share = $totalVotes > 0 ? $votes / $totalVotes : 0; @endphp
                                <div>
                                    <p class="flex justify-between text-sm tabular-nums">
                                        <span class="font-semibold text-ink">{{ trans_choice(':count vote|:count votes', $votes, ['count' => number_format($votes)]) }}</span>
                                        <span class="text-ink-secondary">{{ number_format($share * 100, 1) }}%</span>
                                    </p>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                                        <div class="h-full origin-left rounded-full bg-primary transition-transform duration-[var(--duration-slow)] ease-[var(--ease-out)]" style="transform: scaleX({{ number_format($share, 4, '.', '') }})"></div>
                                    </div>
                                </div>
                            @endif

                            @if ($acceptsVotes)
                                <x-ui.button type="button" block class="mt-auto" id="vote-{{ $nominee->id }}" x-on:click="opener = $el.id" wire:click="select({{ $nominee->id }})" aria-label="Vote for {{ $nominee->displayName() }}">Vote</x-ui.button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($selectedNomineeId !== null && $acceptsVotes)
            <livewire:public.vote-drawer :nominee-id="$selectedNomineeId" :key="'vote-drawer-'.$selectedNomineeId" />
        @endif
    </main>

    <x-public.site-footer />
</div>
