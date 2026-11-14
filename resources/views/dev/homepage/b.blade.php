{{-- Direction B, "Bright celebration": light, colourful, energetic. Dev preview (HomepageDirectionController). --}}
<x-layouts::app :title="'Direction B · '.$design['name']" bundle="home" robots="noindex"
    description="VoteLab homepage direction B (dev preview).">
    <style>
        @keyframes b-float { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-8px) rotate(4deg); } }
    </style>
    @php($bands = ['var(--shape-coral)', 'var(--shape-sun)', 'var(--shape-teal)', 'var(--shape-violet)'])
    <div style="{{ $variables }} --page-focus: {{ $design['colors']['focus-ring'] }}; --search-radius: 9999px;"
        class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)]">
        @include('dev.homepage._header')

        <main id="main">
            {{-- Hero: the party after the announcement. Original geometric shapes, never a traditional pattern. --}}
            <section data-hero aria-labelledby="hero-heading"
                class="relative isolate overflow-hidden bg-[var(--hero-bg)] text-[var(--hero-text)] [--focus-ring:var(--hero-focus)]">
                {{-- Desktop: a cluster beside the text, never behind it (plum on violet would fail contrast). Phones: a strip below. --}}
                <svg class="pointer-events-none absolute right-0 top-16 -z-10 hidden h-[560px] w-[560px] lg:block xl:right-8" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
                    <g class="motion-safe:animate-[b-float_7s_ease-in-out_infinite] [transform-origin:center]">
                        <circle cx="300" cy="90" r="58" fill="var(--shape-sun)" />
                        <path d="M190 40a70 70 0 0 1 0 140z" fill="var(--shape-coral)" />
                    </g>
                    <polygon points="250,190 360,250 240,320" fill="var(--shape-teal)" />
                    <rect x="120" y="230" width="96" height="96" rx="22" fill="var(--shape-violet)" transform="rotate(-14 168 278)" />
                    <g class="motion-safe:animate-[b-float_9s_ease-in-out_infinite_reverse] [transform-origin:center]">
                        <circle cx="96" cy="150" r="22" fill="var(--shape-teal)" />
                        <circle cx="340" cy="350" r="16" fill="var(--shape-coral)" />
                        <rect x="300" y="160" width="18" height="18" rx="4" fill="var(--shape-violet)" transform="rotate(20 309 169)" />
                    </g>
                    <path d="M40 330q40-40 80 0t80 0" fill="none" stroke="var(--shape-coral)" stroke-width="10" stroke-linecap="round" />
                </svg>

                <div class="mx-auto max-w-6xl px-4 pb-10 pt-20 sm:px-6 sm:pb-16 sm:pt-28 lg:pb-24 lg:pt-36">
                    <div class="max-w-2xl">
                        <p class="inline-flex items-center gap-2 rounded-full bg-[var(--hero-raised)] px-3 py-1 text-[13px] font-semibold text-[var(--hero-text)] shadow-[var(--shadow-sm)]">
                            <span class="size-2 rounded-full bg-[var(--shape-teal)]" aria-hidden="true"></span> Awards voting in Ghana
                        </p>
                        <h1 id="hero-heading" class="mt-4 text-[40px] font-semibold leading-[1.04] tracking-tight sm:text-6xl lg:text-[76px]">
                            Back your favourite. <span class="text-[var(--hero-accent)]">Celebrate the win.</span>
                        </h1>
                        <p class="mt-4 max-w-xl text-[17px] leading-relaxed text-[var(--hero-muted)] sm:text-lg">
                            Find your nominee, choose your votes and pay with mobile money or card. Every vote is a confirmed payment, counted once.
                        </p>
                    </div>
                    @include('dev.homepage._search', ['class' => 'mt-6 max-w-2xl sm:rounded-[28px] sm:bg-[var(--hero-raised)] sm:p-5 sm:shadow-[var(--shadow-md)]'])
                </div>
                <svg class="pointer-events-none mx-auto block h-10 w-full max-w-6xl px-4 lg:hidden" viewBox="0 0 360 40" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
                    <circle cx="24" cy="20" r="12" fill="var(--shape-sun)" />
                    <path d="M58 6a14 14 0 0 1 0 28z" fill="var(--shape-coral)" />
                    <polygon points="96,8 122,20 96,32" fill="var(--shape-teal)" />
                    <rect x="138" y="8" width="24" height="24" rx="6" fill="var(--shape-violet)" transform="rotate(-14 150 20)" />
                    <path d="M180 26q12-12 24 0t24 0t24 0" fill="none" stroke="var(--shape-coral)" stroke-width="5" stroke-linecap="round" />
                    <circle cx="280" cy="20" r="7" fill="var(--shape-teal)" />
                    <rect x="300" y="11" width="18" height="18" rx="4" fill="var(--shape-sun)" transform="rotate(18 309 20)" />
                    <circle cx="340" cy="20" r="10" fill="var(--shape-violet)" />
                </svg>
            </section>

            <section id="live" data-reveal aria-labelledby="live-heading" class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-16">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="live-heading" class="text-2xl font-semibold tracking-tight sm:text-3xl">Live now</h2>
                    @if ($repeated)<p class="text-xs text-[var(--text-secondary)]">Grid preview: the live program repeated</p>@endif
                </div>

                @if ($cards === [])
                    <div class="mt-6">@include('dev.homepage._empty')</div>
                @else
                    <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3" aria-label="Programs open for voting">
                        @foreach ($cards as $card)
                            <li class="w-[82%] shrink-0 snap-start sm:w-auto">
                                <a href="{{ $card['url'] }}" class="group flex h-full flex-col overflow-hidden rounded-[24px] bg-[var(--bg-surface)] shadow-[var(--shadow-md)] transition-[transform,box-shadow] duration-[var(--duration-base)] ease-[var(--ease-out)] hover:shadow-[var(--shadow-lg)] motion-safe:hover:-translate-y-1 motion-safe:hover:-rotate-[0.6deg]">
                                    <div class="h-3" style="background: {{ $bands[$loop->index % 4] }}" aria-hidden="true"></div>
                                    <div class="flex flex-1 flex-col p-5">
                                        <div class="flex items-center gap-3">
                                            <x-public.org-mark :organization="$card['organizationModel']" size="size-11" />
                                            <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-[var(--pill-bg)] px-2.5 py-1 text-xs font-semibold text-[var(--pill-fg)]">
                                                <span class="size-1.5 rounded-full bg-[var(--pill-fg)]" aria-hidden="true"></span>Voting open
                                            </span>
                                        </div>
                                        <h3 class="mt-4 text-xl font-semibold leading-snug">{{ $card['title'] }}</h3>
                                        <p class="mt-1 text-sm text-[var(--text-secondary)]">{{ $card['organization'] }} · {{ trans_choice(':count category open|:count categories open', $card['openCategories']) }}</p>
                                        <p class="mt-auto flex flex-wrap items-center gap-x-1.5 pt-5 text-sm">
                                            <x-ui.icon name="clock" class="size-4 text-[var(--brand-text)]" />
                                            @include('dev.homepage._countdown', ['card' => $card])
                                        </p>
                                        <span class="mt-4 inline-flex min-h-11 items-center justify-center rounded-full bg-[var(--brand)] px-4 font-semibold text-[var(--brand-fg)] group-hover:bg-[var(--brand-hover)]">Vote now</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </main>

        @include('dev.homepage._notes')
    </div>
</x-layouts::app>
