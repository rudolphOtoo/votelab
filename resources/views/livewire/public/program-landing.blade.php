@php use App\Support\Money; @endphp

<div>
    <x-public.site-header :organization="$organization" :program="$program" />

    <main id="main" class="mx-auto max-w-5xl px-4 pb-16">
        {{-- Hero --}}
        <header class="pt-4 sm:pt-8">
            @if ($program->banner_url)
                <div class="mb-6 aspect-[16/7] overflow-hidden rounded-[var(--radius-lg)] bg-subtle">
                    <img src="{{ $program->banner_url }}" alt="" width="1200" height="525" fetchpriority="high" decoding="async" class="size-full object-cover">
                </div>
            @endif

            <h1 class="text-3xl font-semibold tracking-tight text-ink sm:text-5xl">{{ $program->title }}</h1>
            @if ($program->tagline)
                <p class="mt-3 max-w-2xl text-lg text-ink-secondary">{{ $program->tagline }}</p>
            @endif

            <p @class([
                'mt-5 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-success-bg text-success-fg' => $status['tone'] === 'success',
                'bg-primary-subtle text-primary-text' => $status['tone'] === 'brand',
                'bg-subtle text-ink-secondary' => in_array($status['tone'], ['neutral', 'warning'], true),
            ])>
                <x-ui.icon name="clock" class="size-4" />
                {{ $status['label'] }}
            </p>
        </header>

        {{-- Find your nominee --}}
        <section aria-labelledby="find-heading" class="mt-8 rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card sm:p-6">
            <h2 id="find-heading" class="text-lg font-semibold text-ink">Find your nominee</h2>
            <form method="get" action="{{ route('public.program', [$organization, $program]) }}" wire:submit="find" role="search" class="mt-3">
                <label for="find-nominee" class="text-sm text-ink-secondary">Type the code on their poster (e.g. AM27) or part of their name.</label>
                <div class="mt-2 flex gap-2">
                    <x-ui.input id="find-nominee" name="q" type="search" wire:model.live.debounce.300ms="q" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="search" placeholder="Code or name" />
                    <x-ui.button type="submit" class="shrink-0"><x-ui.icon name="search" class="size-5" /><span>Find</span></x-ui.button>
                </div>
            </form>

            <div aria-live="polite">
                @if ($searched)
                    @if ($matches->isEmpty())
                        <p class="mt-4 text-sm text-ink-secondary">No nominee matches “{{ trim($q) }}”. Check the code on their poster, or browse the categories below.</p>
                    @else
                        <p class="sr-only">{{ trans_choice(':count nominee found|:count nominees found', $matches->count()) }}</p>
                        <ul class="mt-4 divide-y divide-hairline" wire:key="matches-{{ md5($q) }}">
                            @foreach ($matches as $match)
                                <li>
                                    <a href="{{ route('public.nominee', [$organization, $program, $match->nominee_code]) }}" class="-mx-2 flex min-h-14 items-center gap-3 rounded-[var(--radius-md)] px-2 py-2 hover:bg-hover">
                                        <x-ui.avatar :name="$match->displayName()" :src="$match->avatar_url" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium text-ink">{{ $match->displayName() }}</span>
                                            <span class="block text-sm text-ink-secondary">{{ $match->awardCategory->name }}</span>
                                        </span>
                                        <span class="rounded-[var(--radius-sm)] bg-subtle px-2 py-1 font-mono text-sm font-semibold tracking-wide text-ink">{{ $match->nominee_code }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </section>

        {{-- How to vote --}}
        <section aria-labelledby="how-heading" class="mt-8">
            <h2 id="how-heading" class="sr-only">How to vote</h2>
            <ol class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['Pick a nominee', 'Search by code or name, or open a category.'],
                    ['Choose your votes', 'Each vote has a set price; you see the total before paying.'],
                    ['Pay securely', 'Mobile money (MTN, Telecel, AirtelTigo) or card, through Paystack.'],
                ] as $index => [$step, $detail])
                    <li class="flex gap-3 rounded-[var(--radius-lg)] bg-primary-subtle p-4">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-fg" aria-hidden="true">{{ $index + 1 }}</span>
                        <span>
                            <span class="block font-semibold text-ink">{{ $step }}</span>
                            <span class="block text-sm text-ink-secondary">{{ $detail }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Categories --}}
        <section aria-labelledby="categories-heading" class="mt-10">
            <h2 id="categories-heading" class="text-xl font-semibold text-ink">Categories</h2>

            @if ($categories->isEmpty())
                <x-ui.empty-state title="Categories are on their way" class="mt-4">
                    They'll appear here as soon as voting opens. Check back soon.
                </x-ui.empty-state>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($categories as $category)
                        <li>
                            <a
                                href="{{ route('public.category', [$organization, $program, $category]) }}"
                                wire:navigate
                                class="group flex h-full flex-col rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card transition-colors hover:border-strong"
                            >
                                <x-ui.status-pill :status="$category->status" class="self-start" />
                                <span class="mt-3 text-lg font-semibold text-ink">{{ $category->name }}</span>
                                <span class="mt-1 text-sm text-ink-secondary">
                                    {{ trans_choice(':count nominee|:count nominees', $category->nominees_count) }}
                                    ·
                                    @if ($category->voting_mode->allowsPaidVotes())
                                        <span class="tabular-nums">{{ Money::format($category->price_per_vote) }}</span> per vote
                                    @else
                                        Free to vote
                                    @endif
                                </span>
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-text">
                                    {{ $category->status->isVotingAllowed() ? 'See nominees and vote' : 'See nominees' }}
                                    <x-ui.icon name="chevron-right" class="size-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>

    <x-public.site-footer />
</div>
