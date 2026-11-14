{{-- Direction C, "Modern editorial": typographic, minimal, confident. Dev preview (HomepageDirectionController). --}}
<x-layouts::app :title="'Direction C · '.$design['name']" bundle="home" robots="noindex"
    description="VoteLab homepage direction C (dev preview).">
    <div style="{{ $variables }} --page-focus: {{ $design['colors']['focus-ring'] }}; --search-radius: var(--radius-sm);"
        class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)]">
        @include('dev.homepage._header')

        <main id="main">
            {{-- Hero: type does the work. A 12-column grid, hairline rules, one gold rule. --}}
            <section data-hero aria-labelledby="hero-heading"
                class="bg-[var(--hero-bg)] text-[var(--hero-text)] [--focus-ring:var(--hero-focus)]">
                <div class="mx-auto max-w-6xl px-4 pb-10 pt-20 sm:px-6 sm:pb-16 sm:pt-28 lg:pb-20 lg:pt-36">
                    <div class="flex items-center gap-3 border-b border-[var(--border-hairline)] pb-3 text-[13px] font-medium">
                        <span class="tabular-nums text-[var(--hero-muted)]">01</span>
                        <span>Awards voting in Ghana</span>
                        <span class="ml-auto hidden text-[var(--hero-muted)] sm:inline">Mobile money · Card · Counted once</span>
                    </div>
                    <div class="mt-6 grid gap-8 lg:grid-cols-12 lg:gap-10">
                        <h1 id="hero-heading" class="text-[44px] font-semibold leading-[0.98] tracking-[-0.03em] sm:text-7xl lg:col-span-8 lg:text-[96px]">
                            Every vote counts. <span class="text-[var(--hero-accent)]">Once.</span>
                        </h1>
                        <div class="lg:col-span-4 lg:self-end">
                            <span class="block h-1 w-16 bg-[var(--gold-rule)]" aria-hidden="true"></span>
                            <p class="mt-4 text-[17px] leading-relaxed text-[var(--hero-muted)] sm:text-lg">
                                Vote for your favourites in Ghana's awards. Find your nominee, choose your votes and pay with mobile money or card.
                            </p>
                        </div>
                    </div>
                    @include('dev.homepage._search', ['class' => 'mt-8 border-t border-[var(--hero-text)] pt-5 lg:max-w-3xl'])
                </div>
            </section>

            <section id="live" data-reveal aria-labelledby="live-heading" class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-16">
                <div class="flex items-baseline gap-3 border-b border-[var(--text-primary)] pb-3">
                    <span class="text-[13px] font-medium tabular-nums text-[var(--text-secondary)]">02</span>
                    <h2 id="live-heading" class="text-2xl font-semibold tracking-tight sm:text-4xl">Live now</h2>
                    @if ($repeated)<p class="ml-auto text-xs text-[var(--text-secondary)]">Grid preview: the live program repeated</p>@endif
                </div>

                @if ($cards === [])
                    <div class="mt-6">@include('dev.homepage._empty')</div>
                @else
                    <ol class="-mx-4 mt-2 flex snap-x snap-mandatory gap-6 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-x-10 sm:overflow-visible sm:px-0 lg:grid-cols-3" aria-label="Programs open for voting">
                        @foreach ($cards as $card)
                            <li class="w-[82%] shrink-0 snap-start border-b border-[var(--border-hairline)] sm:w-auto">
                                <a href="{{ $card['url'] }}" class="group flex h-full flex-col py-5">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-medium tabular-nums text-[var(--text-secondary)]">{{ sprintf('%02d', $loop->iteration) }}</span>
                                        <x-public.org-mark :organization="$card['organizationModel']" size="size-9" />
                                        <span class="ml-auto inline-flex items-center rounded-[var(--radius-sm)] bg-[var(--pill-bg)] px-2 py-0.5 text-xs font-semibold text-[var(--pill-fg)]">Voting open</span>
                                    </div>
                                    <h3 class="mt-4 text-2xl font-semibold leading-tight tracking-tight underline-offset-4 decoration-[var(--brand)] decoration-2 group-hover:underline">{{ $card['title'] }}</h3>
                                    <p class="mt-1 text-[var(--text-secondary)]">{{ $card['organization'] }} · {{ trans_choice(':count category open|:count categories open', $card['openCategories']) }}</p>
                                    <p class="mt-auto flex flex-wrap items-baseline gap-x-1.5 pt-5 text-sm">
                                        @include('dev.homepage._countdown', ['card' => $card])
                                    </p>
                                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[var(--brand-text)]">Vote <x-ui.icon name="chevron-right" class="size-4 transition-transform duration-[var(--duration-fast)] motion-safe:group-hover:translate-x-0.5" /></span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </main>

        @include('dev.homepage._notes')
    </div>
</x-layouts::app>
