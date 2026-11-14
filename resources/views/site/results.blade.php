{{-- /results: revealed categories and their winners, by program. Never a hidden or closed-but-unrevealed category. --}}
<x-site.layout :scope="$scope" title="Results" :canonical="route('site.results')"
    description="Winners of awards revealed on VoteLab.">
    <x-site.band eyebrow="Results" title="Winners announced" lead="Every category below has been revealed. Results still hidden by their organizers never appear here." />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        @forelse ($programs as $program)
            <section data-reveal aria-labelledby="program-{{ $loop->index }}" @class(['mt-14' => !$loop->first])>
                <h2 id="program-{{ $loop->index }}" class="text-2xl font-semibold tracking-tight text-ink">
                    <a href="{{ $program['url'] }}" class="underline-offset-4 hover:underline">{{ $program['title'] }}</a>
                </h2>
                <p class="mt-1 text-ink-secondary">{{ $program['organization'] }}</p>
                <ul class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($program['categories'] as $category)
                        <li><x-site.winner-card :category="$category" /></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="rounded-[var(--radius-lg)] border border-dashed border-strong bg-surface px-5 py-10 text-center">
                <p class="font-semibold text-ink">No results have been announced yet.</p>
                <p class="mt-1 text-ink-secondary">Winners appear here as soon as each category is revealed on stage.</p>
                <a href="{{ route('home') }}#live" class="mt-4 inline-flex min-h-11 items-center gap-1 font-semibold text-primary-text underline-offset-4 hover:underline">See what's open for voting <x-ui.icon name="chevron-right" class="size-4" /></a>
            </div>
        @endforelse
    </div>
</x-site.layout>
