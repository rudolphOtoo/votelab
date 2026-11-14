{{-- A legal page: the "Draft — pending legal review" notice while site.legal.draft is true (config/site.php), the
     business details (dummy until replaced, shown as [bracketed] placeholders), and prose. --}}
@props(['scope' => null, 'title', 'route', 'description', 'updated' => '14 November 2026'])
<x-site.layout :scope="$scope" :title="$title" :canonical="route($route)" :description="$description">
    <x-site.band eyebrow="Legal" :title="$title" />
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        @if (config('site.legal.draft'))
            <p role="note" class="mb-8 rounded-[var(--radius-md)] border border-warning bg-warning-bg p-4 font-semibold text-warning-fg">
                Draft — pending legal review. This page explains how VoteLab works today; it is not yet final.
            </p>
        @endif
        <p class="text-sm text-ink-secondary">Last updated {{ $updated }}. Operated by {{ config('site.legal.name') }}, {{ config('site.legal.address') }}, {{ config('site.legal.country') }}.</p>
        <div class="mt-6 space-y-4 text-ink [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc [&_li]:text-ink-secondary [&_p]:text-ink-secondary [&_a]:font-semibold [&_a]:text-primary-text [&_a]:underline">
            {{ $slot }}
        </div>
    </div>
</x-site.layout>
