{{-- Section navigation that looks like tabs (links). Scrolls horizontally on phones. :items="['Label' => url]". --}}
@props(['items', 'current' => null, 'label' => 'Sections'])
<nav aria-label="{{ $label }}" {{ $attributes->merge(['class' => '-mx-4 overflow-x-auto border-b border-hairline px-4 sm:mx-0 sm:px-0']) }}>
    <ul class="flex min-w-max gap-1">
        @foreach ($items as $itemLabel => $href)
            <li><a href="{{ $href }}" @if ($itemLabel === $current) aria-current="page" @endif class="inline-flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium text-ink-secondary hover:text-ink aria-[current=page]:border-primary aria-[current=page]:text-ink">{{ $itemLabel }}</a></li>
        @endforeach
    </ul>
</nav>
