{{-- Dedicated mode with no published program (plan/SITE.md decision 7): branded, never Laravel's welcome page. --}}
<x-site.layout :scope="$organization" :title="$organization->name" description="{{ $organization->name }} has no awards open for voting right now.">
    <x-site.band :eyebrow="$organization->name" title="No live program right now" lead="There are no awards open for voting here at the moment. When the next program opens, it will be right here." />
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <a href="{{ route('site.results') }}" class="inline-flex min-h-11 items-center gap-1 font-semibold text-primary-text underline-offset-4 hover:underline">See past winners <x-ui.icon name="chevron-right" class="size-4" /></a>
    </div>
</x-site.layout>
