{{-- Direction A, "Stage night": dark, dramatic, gold and light. Dev preview (HomepageDirectionController). --}}
<x-layouts::app :title="'Direction A · '.$design['name']" bundle="home" robots="noindex"
    description="VoteLab homepage direction A (dev preview).">
    <style>
        /* The spotlight peaks at 20% gold: supporting text stays above 5.5:1 at its brightest point (measured). */
        @keyframes a-sweep { from { transform: translateX(-60%) rotate(12deg); } to { transform: translateX(160%) rotate(12deg); } }
        @keyframes a-glow { 0%, 100% { opacity: .7; } 50% { opacity: 1; } }
    </style>
    <div style="{{ $variables }} --page-focus: {{ $design['colors']['focus-ring'] }}; --search-radius: var(--radius-md);"
        class="min-h-screen bg-[var(--bg-canvas)] text-[var(--text-primary)]">
        @include('dev.homepage._header')

        <main id="main">
            {{-- Hero: house lights down, a warm spotlight, gold on basalt. --}}
            <section data-hero aria-labelledby="hero-heading"
                class="relative isolate overflow-hidden bg-[var(--hero-bg)] text-[var(--hero-text)] [--focus-ring:var(--hero-focus)]">
                <svg class="pointer-events-none absolute inset-0 -z-10 h-full w-full" viewBox="0 0 1440 720" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
                    <defs>
                        <radialGradient id="a-spot" cx="72%" cy="8%" r="70%">
                            <stop offset="0" stop-color="#fbbf24" stop-opacity=".2" />
                            <stop offset=".45" stop-color="#f59e0b" stop-opacity=".08" />
                            <stop offset="1" stop-color="#121316" stop-opacity="0" />
                        </radialGradient>
                        <linearGradient id="a-beam" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#fde68a" stop-opacity=".22" />
                            <stop offset="1" stop-color="#fde68a" stop-opacity="0" />
                        </linearGradient>
                        <pattern id="a-lattice" width="36" height="36" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                            <rect x="16" y="16" width="4" height="4" fill="#fbbf24" fill-opacity=".14" />
                        </pattern>
                    </defs>
                    <rect width="1440" height="720" fill="url(#a-lattice)" />
                    <rect width="1440" height="720" fill="url(#a-spot)" class="motion-safe:animate-[a-glow_6s_ease-in-out_infinite]" />
                    <polygon points="980,0 1060,0 1240,720 760,720" fill="url(#a-beam)" />
                    <polygon points="1120,0 1180,0 1440,620 1440,720 1180,720" fill="url(#a-beam)" opacity=".7" />
                    <polygon points="840,0 880,0 620,720 420,720" fill="url(#a-beam)" opacity=".45" />
                </svg>
                <div class="pointer-events-none absolute inset-y-0 -left-1/3 -z-10 w-1/3 bg-gradient-to-r from-transparent via-[#fde68a]/10 to-transparent motion-safe:animate-[a-sweep_9s_ease-in-out_infinite]" aria-hidden="true"></div>

                <div class="mx-auto grid max-w-6xl gap-8 px-4 pb-10 pt-20 sm:px-6 sm:pb-16 sm:pt-28 lg:grid-cols-12 lg:pb-24 lg:pt-36">
                    <div class="lg:col-span-7">
                        <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-[var(--hero-accent)]">Awards voting in Ghana</p>
                        <h1 id="hero-heading" class="mt-3 text-[40px] font-semibold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">
                            Vote for your favourites. <span class="text-[var(--hero-accent)]">Watch them win.</span>
                        </h1>
                        <p class="mt-4 max-w-xl text-[17px] leading-relaxed text-[var(--hero-muted)] sm:text-lg">
                            Find your nominee, choose your votes and pay with mobile money or card. Every vote is a confirmed payment, counted once.
                        </p>
                    </div>
                    @include('dev.homepage._search', ['row' => 'sm:flex-row lg:flex-col', 'class' => 'lg:col-span-5 lg:self-end rounded-[var(--radius-lg)] lg:bg-[var(--hero-raised)]/80 lg:p-6 lg:ring-1 lg:ring-white/10'])
                </div>
            </section>

            {{-- Live now: calm, light, easy to read and tap. --}}
            <section id="live" data-reveal aria-labelledby="live-heading" class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-16">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="live-heading" class="flex items-center gap-2.5 text-2xl font-semibold tracking-tight sm:text-3xl">
                        <span class="relative flex size-2.5" aria-hidden="true">
                            <span class="absolute inline-flex size-full rounded-full bg-[#d97706] opacity-60 motion-safe:animate-ping"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-[#d97706]"></span>
                        </span>
                        Live now
                    </h2>
                    @if ($repeated)<p class="text-xs text-[var(--text-secondary)]">Grid preview: the live program repeated</p>@endif
                </div>

                @if ($cards === [])
                    <div class="mt-6">@include('dev.homepage._empty')</div>
                @else
                    <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3" aria-label="Programs open for voting">
                        @foreach ($cards as $card)
                            <li class="w-[82%] shrink-0 snap-start sm:w-auto">
                                <a href="{{ $card['url'] }}" class="group flex h-full flex-col rounded-[var(--radius-lg)] border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-5 shadow-[var(--shadow-sm)] transition-[transform,box-shadow] duration-[var(--duration-base)] ease-[var(--ease-out)] hover:shadow-[var(--shadow-lg)] motion-safe:hover:-translate-y-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <x-public.org-mark :organization="$card['organizationModel']" size="size-12" />
                                        <span class="inline-flex items-center rounded-full bg-[var(--pill-bg)] px-2.5 py-1 text-xs font-semibold text-[var(--pill-fg)]">Voting open</span>
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold leading-snug">{{ $card['title'] }}</h3>
                                    <p class="mt-1 text-sm text-[var(--text-secondary)]">{{ $card['organization'] }} · {{ trans_choice(':count category open|:count categories open', $card['openCategories']) }}</p>
                                    <p class="mt-auto flex flex-wrap items-center gap-x-1.5 border-t border-[var(--border-hairline)] pt-4 text-sm">
                                        <x-ui.icon name="clock" class="size-4 text-[var(--brand-text)]" />
                                        @include('dev.homepage._countdown', ['card' => $card])
                                    </p>
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
