{{-- A revealed category and its winner(s), with a gold accent. Only revealed categories ever reach this card
     (SiteDirectory::winners). No vote counts. --}}
@props(['category', 'headingLevel' => 3])
@php($winner = $category['winners'][0] ?? null)
<article class="flex h-full flex-col overflow-hidden rounded-[var(--radius-lg)] border border-hairline bg-surface shadow-[var(--shadow-sm)]">
    <div class="relative">
        @if ($winner)
            <x-ui.nominee-photo :src="$winner['photo']" :srcset="$winner['srcset']" :name="$winner['name']" alt="" class="rounded-none" sizes="(min-width: 1024px) 18rem, (min-width: 640px) 45vw, 70vw" />
        @else
            <div class="aspect-[4/5] w-full bg-subtle"></div>
        @endif
        <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-hero px-2.5 py-1 text-xs font-semibold text-hero-accent">
            <svg viewBox="0 0 16 16" class="size-3.5" aria-hidden="true" focusable="false"><path d="M8 1.5l1.9 4 4.4.5-3.3 3 .9 4.4L8 11.2 4.1 13.4 5 9 1.7 6l4.4-.5z" fill="currentColor"/></svg>
            Winner
        </span>
    </div>
    <div class="flex flex-1 flex-col border-t-4 border-gold p-4">
        <p class="text-sm text-ink-secondary"><a href="{{ $category['url'] }}" class="underline-offset-4 hover:underline">{{ $category['category'] }}</a></p>
        <h{{ $headingLevel }} class="mt-1 text-lg font-semibold leading-snug text-ink">
            @forelse ($category['winners'] as $w)
                <a href="{{ $w['url'] }}" class="underline-offset-4 hover:underline">{{ $w['name'] }}</a>@if (!$loop->last)<span class="font-normal text-ink-secondary"> and </span>@endif
            @empty
                No winner announced
            @endforelse
        </h{{ $headingLevel }}>
        @if (count($category['winners']) > 1)<p class="mt-1 text-sm text-ink-secondary">A tie for first place.</p>@endif
        <p class="mt-auto pt-3 text-sm text-ink-secondary">{{ $category['program'] }} · {{ $category['organization'] }}</p>
    </div>
</article>
