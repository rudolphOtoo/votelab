{{-- The dark band at the top of an inner page: eyebrow, title and an optional lead. The header sits on it. --}}
@props(['eyebrow' => null, 'title', 'lead' => null])
<section data-hero aria-labelledby="page-title" class="relative isolate overflow-hidden bg-hero text-hero-ink [--focus-ring:var(--hero-focus)]">
    <x-site.hero-art />
    <div class="mx-auto max-w-6xl px-4 pb-10 pt-24 sm:px-6 sm:pb-14 sm:pt-32">
        @if ($eyebrow)<p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-hero-accent">{{ $eyebrow }}</p>@endif
        <h1 id="page-title" class="mt-3 max-w-3xl text-4xl font-semibold leading-[1.08] tracking-tight sm:text-5xl">{{ $title }}</h1>
        @if ($lead)<p class="mt-4 max-w-2xl text-[17px] leading-relaxed text-hero-muted sm:text-lg">{{ $lead }}</p>@endif
        {{ $slot }}
    </div>
</section>
