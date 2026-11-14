{{-- Shared header: transparent over the hero, solid once the hero has scrolled past (resources/js/home.js). The focus
     ring follows the background: the hero's colour while transparent, the page's once solid. --}}
<header data-site-header
    class="fixed inset-x-0 top-0 z-[var(--z-sticky)] text-[var(--hero-text)] [--focus-ring:var(--hero-focus)] transition-[background-color,color,box-shadow] duration-[var(--duration-base)] data-scrolled:bg-[var(--bg-surface)] data-scrolled:text-[var(--text-primary)] data-scrolled:shadow-[var(--shadow-sm)] data-scrolled:[--focus-ring:var(--page-focus)]">
    <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-2 sm:px-6">
        <a href="{{ request()->url() }}" class="flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] pr-1 text-lg font-semibold tracking-tight">
            @include('dev.homepage._mark', ['class' => 'size-7'])
            VoteLab
        </a>
        <nav aria-label="Main" class="ml-auto">
            <ul class="flex items-center text-sm font-medium">
                <li><a href="#live" class="inline-flex min-h-11 items-center rounded-full px-3 hover:underline">Vote</a></li>
                <li><a href="#" class="inline-flex min-h-11 items-center rounded-full px-3 hover:underline">Results</a></li>
                <li class="hidden sm:block"><a href="#" class="inline-flex min-h-11 items-center rounded-full px-3 hover:underline">Help</a></li>
                <li class="hidden sm:block"><a href="#" class="inline-flex min-h-11 items-center rounded-full px-3 hover:underline">For organizers</a></li>
            </ul>
        </nav>
    </div>
</header>
