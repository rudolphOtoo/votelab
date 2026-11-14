{{-- The VoteLab homepage (plan/SITE.md; homepage brief, direction A "Stage night"). --}}
<x-site.layout :scope="$scope"
    description="Vote for your favourites in Ghana's awards with mobile money or card. Every vote is a confirmed payment, counted once."
    :canonical="route('home')"
:meta="[
        'og:title' => 'VoteLab: vote for your favourites',
        'og:description' => 'Vote for your favourites in Ghana\'s awards with mobile money or card.',
        'og:type' => 'website',
        'og:url' => route('home'),
        'og:image' => $shareImage,
        'og:image:width' => (string) \App\Support\Site\SiteShareImage::WIDTH,
        'og:image:height' => (string) \App\Support\Site\SiteShareImage::HEIGHT,
        'og:image:alt' => 'VoteLab: Vote for your favourites. Watch them win.',
        'twitter:card' => 'summary_large_image',
    ]">

    {{-- 1. Hero --}}
    <section data-hero aria-labelledby="hero-heading" class="relative isolate overflow-hidden bg-hero text-hero-ink [--focus-ring:var(--hero-focus)]">
        <x-site.hero-art />
        <div class="mx-auto grid max-w-6xl gap-8 px-4 pb-10 pt-20 sm:px-6 sm:pb-16 sm:pt-28 lg:grid-cols-12 lg:pb-24 lg:pt-36">
            <div class="lg:col-span-7">
                <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-hero-accent">Awards voting in Ghana</p>
                <h1 id="hero-heading" class="mt-3 text-[40px] font-semibold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">
                    Vote for your favourites. <span class="text-hero-accent">Watch them win.</span>
                </h1>
                <p class="mt-4 max-w-xl text-[17px] leading-relaxed text-hero-muted sm:text-lg">
                    Find your nominee, choose your votes and pay with mobile money or card. Every vote is a confirmed payment, counted once.
                </p>
            </div>
            <x-site.search :query="$query" row="sm:flex-row lg:flex-col"
                class="rounded-[var(--radius-lg)] lg:col-span-5 lg:self-end lg:bg-hero-raised lg:p-6 lg:ring-1 lg:ring-hero-field-border/40" />
        </div>
    </section>

    {{-- Search results (?q=): a name, or a code more than one program has. Sorted by name, never by votes. --}}
    @if ($matches !== null)
        <section id="search-results" aria-labelledby="search-heading" class="mx-auto max-w-6xl scroll-mt-20 px-4 pt-10 sm:px-6">
            <h2 id="search-heading" class="text-xl font-semibold text-ink">Nominees matching “{{ $query }}”</h2>
            @if ($matches === [])
                <p class="mt-2 text-ink-secondary">No nominee matches. Check the code on the nominee's poster, or try part of their name.</p>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($matches as $match)
                        <li>
                            <a href="{{ $match['url'] }}" class="flex min-h-11 flex-col rounded-[var(--radius-md)] border border-hairline bg-surface p-4 hover:border-strong">
                                <span class="font-semibold text-ink">{{ $match['name'] }} <span class="font-mono text-sm font-normal text-ink-secondary">({{ $match['code'] }})</span></span>
                                <span class="mt-1 text-sm text-ink-secondary">{{ $match['category'] }} · {{ $match['program'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    {{-- 2. Live now --}}
    <section id="live" data-reveal aria-labelledby="live-heading" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-10 sm:px-6 sm:py-16">
        <h2 id="live-heading" class="flex items-center gap-2.5 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
            <span class="relative flex size-2.5" aria-hidden="true">
                <span class="absolute inline-flex size-full rounded-full bg-gold opacity-60 motion-safe:animate-ping"></span>
                <span class="relative inline-flex size-2.5 rounded-full bg-gold"></span>
            </span>
            Live now
        </h2>
        @if ($live === [])
            <div class="mt-6 rounded-[var(--radius-lg)] border border-dashed border-strong bg-surface px-5 py-8 text-center">
                <x-ui.icon name="clock" class="mx-auto size-8 text-ink-secondary" />
                <p class="mt-3 font-semibold text-ink">No awards are open for voting right now.</p>
                <p class="mt-1 text-ink-secondary">When a program opens for voting, it appears here. In the meantime, see who won recently.</p>
                <a href="{{ route('site.results') }}" class="mt-4 inline-flex min-h-11 items-center gap-1 font-semibold text-primary-text underline-offset-4 hover:underline">See recent winners <x-ui.icon name="chevron-right" class="size-4" /></a>
            </div>
        @else
            <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3" aria-label="Programs open for voting">
                @foreach ($live as $program)
                    <li class="w-[82%] shrink-0 snap-start sm:w-auto"><x-site.program-card :program="$program" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 3. How it works --}}
    <section data-reveal aria-labelledby="how-heading" class="border-y border-hairline bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <h2 id="how-heading" class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">How voting works</h2>
            <ol class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['Find your nominee', 'Search their code or name, or open the link they shared.', 'find'],
                    ['Choose your votes', 'Pick how many. You see the total, fee included, before you pay.', 'votes'],
                    ['Pay with mobile money or card', 'Approve the prompt on your phone. Your votes count once Paystack confirms the payment.', 'pay'],
                ] as [$title, $text, $icon])
                    <li class="flex gap-4 sm:flex-col">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-[var(--radius-lg)] bg-hero text-hero-accent" aria-hidden="true">
                            @if ($icon === 'find')
                                <svg viewBox="0 0 32 32" class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="14" cy="14" r="8" /><path d="M20 20l7 7M10.5 14h7M14 10.5v7" /></svg>
                            @elseif ($icon === 'votes')
                                <svg viewBox="0 0 32 32" class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="9" width="22" height="16" rx="4" /><path d="M11 17h4M13 15v4M19 17h3" /><path d="M11 5h10" /></svg>
                            @else
                                <svg viewBox="0 0 32 32" class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="14" height="26" rx="3" /><path d="M12.5 16.5l2.5 2.5 5-5.5" /><path d="M14 25h4" /></svg>
                            @endif
                        </span>
                        <div>
                            <h3 class="font-semibold text-ink"><span class="text-ink-secondary">{{ $loop->iteration }}.</span> {{ $title }}</h3>
                            <p class="mt-1 text-ink-secondary">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <p class="mt-8 inline-flex items-center gap-2 rounded-full bg-pill px-3 py-1.5 text-sm font-semibold text-pill-fg">
                <x-ui.icon name="lock" class="size-4" /> Votes are final once counted.
            </p>
        </div>
    </section>

    {{-- 4. Winners: revealed categories only, ever. --}}
    <section data-reveal aria-labelledby="winners-heading" class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 id="winners-heading" class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Results announced</h2>
            <a href="{{ route('site.results') }}" class="inline-flex min-h-11 shrink-0 items-center gap-1 whitespace-nowrap text-sm font-semibold text-primary-text underline-offset-4 hover:underline">All results <x-ui.icon name="chevron-right" class="size-4" /></a>
        </div>
        @if ($winners === [])
            <p class="mt-4 text-ink-secondary">Winners appear here as soon as each category is revealed on stage.</p>
        @else
            <ul class="-mx-4 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 lg:grid-cols-4" aria-label="Recent winners">
                @foreach ($winners as $category)
                    <li class="w-[70%] shrink-0 snap-start sm:w-auto"><x-site.winner-card :category="$category" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 5. Why people trust VoteLab: each point is something the system does. --}}
    <section data-reveal aria-labelledby="trust-heading" class="border-t border-hairline bg-surface">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <span class="block h-1 w-16 bg-gold" aria-hidden="true"></span>
                <h2 id="trust-heading" class="mt-4 text-4xl font-semibold leading-[1.02] tracking-[-0.03em] text-ink sm:text-5xl">Every vote counts. <span class="text-primary-text">Once.</span></h2>
            </div>
            <ul class="grid gap-6 sm:grid-cols-2 lg:col-span-7">
                @foreach ([
                    ['Every vote is a confirmed payment', 'Paid votes are counted only after Paystack confirms the exact amount, and each payment is counted once, however many times the confirmation arrives.'],
                    ['Results checked against the ledger', 'Every vote is recorded in a ledger. Totals are checked against it before each reveal and every night.'],
                    ['Hidden results stay hidden', 'While an organizer keeps results hidden, no page, share image or live update shows counts or rankings, right up to the reveal.'],
                    ['A reference for every payment', 'Every payment has a reference you can quote if anything looks wrong.'],
                ] as [$title, $text])
                    <li class="border-t border-hairline pt-4">
                        <h3 class="font-semibold text-ink">{{ $title }}</h3>
                        <p class="mt-1 text-ink-secondary">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 6. For organizers --}}
    <section data-reveal aria-labelledby="organizers-heading" class="bg-hero text-hero-ink [--focus-ring:var(--hero-focus)]">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-hero-accent">For organizers</p>
                <h2 id="organizers-heading" class="mt-3 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Run your awards on VoteLab.</h2>
                <p class="mt-4 text-hero-muted">{{ config('site.fee') }}</p>
                <a href="{{ route('site.request-access') }}" class="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-hero-accent px-5 font-semibold text-hero-accent-fg hover:brightness-110">Request access</a>
                <a href="{{ route('site.organizers') }}" class="mt-3 flex min-h-11 items-center gap-1 text-sm font-medium underline-offset-4 hover:underline">What organizers get <x-ui.icon name="chevron-right" class="size-4" /></a>
            </div>
            <ul class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:col-span-7">
                @foreach ([
                    ['A dashboard for your team', 'Categories, nominees, photos and roles for owners, admins and staff.'],
                    ['A launch checklist', 'Nothing goes live until prices, photos, codes and payouts are ready.'],
                    ['Share kits for WhatsApp', 'A link, a code and an image for every nominee.'],
                    ['A live stage screen', 'Standings for the venue, then a reveal moment for each winner.'],
                    ['Audited results and export', 'Totals checked against the ledger, every change logged, CSV export.'],
                    ['Payouts through Paystack', 'Paid votes settle to your own Paystack subaccount.'],
                ] as [$title, $text])
                    <li class="border-t border-hero-raised pt-4">
                        <h3 class="font-semibold">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-hero-muted">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-site.layout>
