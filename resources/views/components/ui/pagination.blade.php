{{-- Pagination for a LengthAwarePaginator: previous / page x of y / next on phones; from sm up, the first and last
     pages, the current page with its neighbours, and an ellipsis for each gap. --}}
@props(['paginator'])
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = array_values(array_unique(array_filter([1, $current - 1, $current, $current + 1, $last], static fn(int $p): bool => $p >= 1 && $p <= $last)));
    sort($pages);
@endphp
@if ($paginator->hasPages())
    <nav aria-label="Pagination" {{ $attributes->merge(['class' => 'flex items-center justify-between gap-2']) }}>
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-11 items-center gap-1 px-3 text-sm text-ink-tertiary" aria-disabled="true"><x-ui.icon name="chevron-left" class="size-4" /> Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center gap-1 rounded-[var(--radius-md)] px-3 text-sm font-medium text-ink hover:bg-hover"><x-ui.icon name="chevron-left" class="size-4" /> Previous</a>
        @endif
        <p class="text-sm text-ink-secondary sm:hidden">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</p>
        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <li aria-hidden="true" class="inline-flex size-11 items-center justify-center text-sm text-ink-tertiary">…</li>
                @endif
                <li>
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="inline-flex size-11 items-center justify-center rounded-[var(--radius-md)] bg-primary text-sm font-semibold text-primary-fg">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="inline-flex size-11 items-center justify-center rounded-[var(--radius-md)] text-sm text-ink hover:bg-hover" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center gap-1 rounded-[var(--radius-md)] px-3 text-sm font-medium text-ink hover:bg-hover">Next <x-ui.icon name="chevron-right" class="size-4" /></a>
        @else
            <span class="inline-flex min-h-11 items-center gap-1 px-3 text-sm text-ink-tertiary" aria-disabled="true">Next <x-ui.icon name="chevron-right" class="size-4" /></span>
        @endif
    </nav>
@endif
