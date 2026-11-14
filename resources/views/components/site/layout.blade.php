{{--
  Public site layout (plan/SITE.md, homepage brief direction A "Stage night"): the site theme, the plain-JavaScript
  `home` bundle (no Livewire or Alpine), the shared header and footer. Every page opens with a dark band ([data-hero])
  that the header sits on transparently until the band scrolls past.
--}}
@props(['title' => null, 'description' => null, 'canonical' => null, 'robots' => null, 'meta' => [], 'scope' => null])
<x-layouts::app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots" :meta="$meta"
    theme="site" bundle="home" :brand-organization="null">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[var(--z-toast)] focus:rounded-[var(--radius-md)] focus:bg-surface focus:px-4 focus:py-3 focus:text-ink focus:shadow-[var(--shadow-lg)]">Skip to content</a>
    <x-site.header :scope="$scope" />
    <main id="main" tabindex="-1" class="outline-none">
        {{ $slot }}
    </main>
    <x-site.footer :scope="$scope" />
</x-layouts::app>
