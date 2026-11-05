<main
    class="mx-auto max-w-5xl px-4 pb-24"
    @if ($standings !== null) wire:poll.3s.visible @else wire:poll.15s.visible @endif
>
    <nav class="pt-6 text-sm" aria-label="Breadcrumb">
        <a href="{{ route('public.program', [$organization, $program]) }}" wire:navigate class="text-ink-secondary underline-offset-4 hover:underline">
            ← {{ $program->title }}
        </a>
    </nav>

    <header class="py-8">
        <x-public.status-pill :status="$category->status" />
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-4xl">{{ $category->name }}</h1>
        @if ($category->description)
            <p class="mt-2 max-w-2xl text-ink-secondary">{{ $category->description }}</p>
        @endif

        @if ($category->status === \App\Enums\CategoryStatus::Embargoed)
            <p class="mt-4 inline-flex items-center gap-2 rounded-[var(--radius-md)] bg-subtle px-3 py-2 text-sm text-ink">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                Blind voting: scores are hidden until the finale.
            </p>
        @endif
    </header>

    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($nominees as $nominee)
            @php $votes = $standings[$nominee->id] ?? null; @endphp
            <li wire:key="nominee-{{ $nominee->id }}" class="flex flex-col overflow-hidden rounded-[var(--radius-lg)] border border-hairline bg-surface">
                <div class="aspect-[4/5] bg-subtle">
                    @if ($nominee->avatar_url)
                        <img src="{{ $nominee->avatar_url }}" alt="" class="size-full object-cover" loading="lazy" width="400" height="500">
                    @else
                        <div class="flex size-full items-center justify-center text-3xl font-semibold text-ink-tertiary" aria-hidden="true">
                            {{ \Illuminate\Support\Str::of($nominee->displayName())->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                        </div>
                    @endif
                </div>

                <div class="flex flex-1 flex-col p-3">
                    <p class="font-semibold leading-tight text-ink">{{ $nominee->displayName() }}</p>
                    <p class="mt-0.5 font-mono text-xs text-ink-tertiary">{{ $nominee->nominee_code }}</p>

                    @if ($votes !== null)
                        @php $share = $totalVotes > 0 ? $votes / $totalVotes : 0; @endphp
                        <p class="mt-2 text-sm tabular-nums text-ink-secondary">{{ trans_choice(':count vote|:count votes', $votes, ['count' => number_format($votes)]) }}</p>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                            <div class="h-full origin-left rounded-full bg-primary transition-transform duration-[250ms] ease-[cubic-bezier(0.16,1,0.3,1)]" style="transform: scaleX({{ number_format($share, 4, '.', '') }})"></div>
                        </div>
                    @endif

                    @if ($acceptsVotes)
                        <button
                            type="button"
                            wire:click="select({{ $nominee->id }})"
                            class="mt-3 min-h-11 rounded-[var(--radius-md)] bg-primary px-3 text-sm font-semibold text-primary-fg transition-transform active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focused"
                        >
                            Vote for {{ \Illuminate\Support\Str::of($nominee->displayName())->before(' ') }}
                        </button>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($selectedNomineeId !== null && $acceptsVotes)
        <livewire:public.vote-drawer :nominee-id="$selectedNomineeId" :key="'vote-drawer-'.$selectedNomineeId" />
    @endif
</main>
