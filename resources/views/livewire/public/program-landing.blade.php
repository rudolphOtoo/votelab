@php use App\Support\Money; @endphp

<main class="mx-auto max-w-5xl px-4 pb-16">
    <header class="py-10 sm:py-14">
        <p class="text-sm font-medium text-ink-secondary">{{ $organization->name }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink sm:text-5xl">{{ $program->title }}</h1>

        @if ($program->tagline)
            <p class="mt-3 max-w-2xl text-lg text-ink-secondary">{{ $program->tagline }}</p>
        @endif

        @if ($program->ends_at && $program->ends_at->isFuture())
            <div
                class="mt-6 inline-flex items-center gap-2 rounded-[var(--radius-md)] border border-hairline bg-surface px-4 py-2 text-sm"
                x-data="{
                    endsAt: new Date(@js($program->ends_at->toIso8601String())).getTime(),
                    left: '',
                    tick() {
                        const s = Math.max(0, Math.floor((this.endsAt - Date.now()) / 1000));
                        const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
                        this.left = s === 0 ? 'Voting has ended' : `${d}d ${h}h ${m}m left to vote`;
                    },
                }"
                x-init="tick(); setInterval(() => tick(), 30000)"
            >
                <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>
                <span class="tabular-nums" x-text="left">Voting closes {{ $program->ends_at->toDayDateTimeString() }}</span>
            </div>
        @endif
    </header>

    <section aria-labelledby="categories-heading">
        <h2 id="categories-heading" class="text-lg font-semibold text-ink">Categories</h2>

        @if ($categories->isEmpty())
            <p class="mt-4 text-ink-secondary">Categories will appear here once voting opens.</p>
        @else
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    <li>
                        <a
                            href="{{ route('public.category', [$organization, $program, $category]) }}"
                            wire:navigate
                            class="flex h-full flex-col rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 transition-colors hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focused"
                        >
                            <x-ui.status-pill :status="$category->status" class="self-start" />
                            <span class="mt-3 text-base font-semibold text-ink">{{ $category->name }}</span>
                            <span class="mt-1 text-sm text-ink-secondary">
                                {{ trans_choice(':count nominee|:count nominees', $category->nominees_count) }}
                                · {{ $category->voting_mode->label() }}
                                @if ($category->voting_mode->allowsPaidVotes())
                                    · {{ Money::format($category->price_per_vote) }} per vote
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</main>
