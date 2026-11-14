{{-- A program open for voting: logo, name, organization, status and "Closes in 2d 4h" (Accra time; kept current by
     resources/js/home.js). No vote counts, ever. --}}
@props(['program'])
@php
    $closesAt = $program['closesAt'] !== null ? \Carbon\CarbonImmutable::parse($program['closesAt']) : null;
@endphp
<a href="{{ $program['url'] }}" class="group flex h-full flex-col rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-[var(--shadow-sm)] transition-[transform,box-shadow] duration-[var(--duration-base)] ease-[var(--ease-out)] hover:shadow-[var(--shadow-lg)] motion-safe:hover:-translate-y-1">
    @if ($program['banner'])
        <img src="{{ $program['banner'] }}" alt="" width="640" height="200" loading="lazy" decoding="async" class="-mx-5 -mt-5 mb-4 aspect-[16/5] w-[calc(100%+2.5rem)] max-w-none rounded-t-[var(--radius-lg)] bg-subtle object-cover">
    @endif
    <div class="flex items-start justify-between gap-3">
        <x-site.org-logo :logo="$program['logo']" :name="$program['organization']" />
        <span class="inline-flex items-center rounded-full bg-pill px-2.5 py-1 text-xs font-semibold text-pill-fg">Voting open</span>
    </div>
    <h3 class="mt-4 text-lg font-semibold leading-snug text-ink">{{ $program['title'] }}</h3>
    <p class="mt-1 text-sm text-ink-secondary">{{ $program['organization'] }} · {{ trans_choice(':count category open|:count categories open', $program['openCategories']) }}</p>
    <p class="mt-auto flex flex-wrap items-center gap-x-1.5 border-t border-hairline pt-4 text-sm text-ink">
        <x-ui.icon name="clock" class="size-4 text-primary-text" />
        @if ($closesAt)
            <span class="font-semibold tabular-nums" data-closes-at="{{ $closesAt->toIso8601String() }}">{{ \App\Support\Site\Countdown::closesIn($closesAt) }}</span>
            <span class="basis-full text-ink-secondary">{{ \App\Support\PublicSchedule::format($closesAt) }} (Accra time)</span>
        @else
            <span class="font-semibold">Voting open</span>
        @endif
    </p>
</a>
