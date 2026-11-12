{{-- Program sub-navigation: a horizontally scrolling tab bar on phones, no hidden menus. --}}
@props(['program', 'current'])

@php
    $tabs = [
        'overview' => ['Overview', route('dashboard.programs.show', $program)],
        'categories' => ['Categories & nominees', route('dashboard.programs.categories', $program)],
        'results' => ['Results & money', route('dashboard.programs.results', $program)],
    ];
@endphp

<div class="mb-6">
    <p class="text-sm"><a href="{{ route('dashboard') }}" class="text-ink-secondary underline underline-offset-4">Dashboard</a></p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ $program->title }}</h1>
    <p class="mt-1 text-sm text-ink-secondary">{{ $program->status->label() }}</p>
    <nav aria-label="Program sections" class="-mx-4 mt-4 overflow-x-auto border-b border-hairline px-4">
        <ul class="flex min-w-max gap-1">
            @foreach ($tabs as $key => [$label, $href])
                <li>
                    <a href="{{ $href }}" @if ($key === $current) aria-current="page" @endif
                       class="inline-flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm text-ink-secondary hover:text-ink aria-[current=page]:border-ink aria-[current=page]:font-medium aria-[current=page]:text-ink">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</div>
