{{-- :items="['Dashboard' => url, 'Campus Awards' => url, 'Categories' => null]" — the last item is the current page. --}}
@props(['items'])
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'text-sm']) }}>
    <ol class="flex flex-wrap items-center gap-1 text-ink-secondary">
        @foreach ($items as $label => $href)
            <li class="flex items-center gap-1">
                @if (!$loop->first)<x-ui.icon name="chevron-right" class="size-4 text-ink-tertiary" />@endif
                @if ($href && !$loop->last)
                    <a href="{{ $href }}" class="rounded-sm underline-offset-4 hover:text-ink hover:underline">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" class="font-medium text-ink" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
