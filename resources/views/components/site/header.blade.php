{{-- Site header: transparent over the page's dark band, solid once it scrolls past (resources/js/home.js). The focus
     ring follows the background. In dedicated mode it names the organization. --}}
@props(['scope' => null])
@php
    $links = [
        ['Vote', route('home').'#live', 'home', true],
        ['Results', route('site.results'), 'site.results', true],
        ['Help', route('site.help'), 'site.help', false],
        ['For organizers', route('site.organizers'), 'site.organizers', false],
    ];
@endphp
<header data-site-header
    class="fixed inset-x-0 top-0 z-[var(--z-sticky)] text-hero-ink [--focus-ring:var(--hero-focus)] transition-[background-color,color,box-shadow] duration-[var(--duration-base)] data-scrolled:bg-surface data-scrolled:text-ink data-scrolled:shadow-[var(--shadow-sm)] data-scrolled:[--focus-ring:var(--brand)]">
    <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-2 sm:px-6">
        <a href="{{ route('home') }}" class="flex min-h-11 min-w-0 items-center gap-2 rounded-[var(--radius-sm)] pr-1 text-lg font-semibold tracking-tight">
            @if ($scope)
                <x-public.org-mark :organization="$scope" size="size-8" />
                <span class="truncate">{{ $scope->name }}</span>
            @else
                <x-site.mark />
                VoteLab
            @endif
        </a>
        <nav aria-label="Main" class="ml-auto">
            <ul class="flex items-center text-sm font-medium">
                @foreach ($links as [$label, $href, $route, $onPhones])
                    <li @class(['hidden sm:block' => !$onPhones])>
                        <a href="{{ $href }}" @if (request()->routeIs($route) && $route !== 'home') aria-current="page" @endif
                            class="inline-flex min-h-11 items-center rounded-full px-3 underline-offset-4 hover:underline aria-[current=page]:underline">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>
