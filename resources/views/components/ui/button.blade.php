{{--
  Buttons and button-styled links. variant: primary | secondary | ghost | danger. size: sm | md | lg.
  loading: true for a static busy state, or a Livewire action name ("save") to show the spinner while it runs.
  Every size keeps a 44 px minimum tap target.
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'loading' => null, 'block' => false, 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'bg-primary text-primary-fg hover:bg-primary-hover shadow-card',
        'secondary' => 'border border-strong bg-surface text-ink hover:bg-hover',
        'ghost' => 'text-ink hover:bg-hover',
        'danger' => 'bg-danger text-white hover:bg-danger-fg shadow-card',
    ];
    $sizes = ['sm' => 'min-h-11 px-3 text-sm', 'md' => 'min-h-11 px-4 text-base', 'lg' => 'min-h-12 px-5 text-base sm:text-lg'];
    $classes = 'inline-flex select-none items-center justify-center gap-2 rounded-[var(--radius-md)] font-semibold transition-[transform,background-color] duration-[var(--duration-fast)] active:scale-[0.98] disabled:pointer-events-none disabled:opacity-55 aria-disabled:pointer-events-none aria-disabled:opacity-55 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).($block ? ' w-full' : '');
    $wireTarget = is_string($loading) ? $loading : null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}
        @if ($loading === true) disabled aria-busy="true" @endif
        @if ($wireTarget) wire:loading.attr="disabled" wire:loading.attr.aria-busy="true" wire:target="{{ $wireTarget }}" @endif>
        @if ($loading === true)
            <x-ui.spinner />
        @elseif ($wireTarget)
            <x-ui.spinner wire:loading wire:target="{{ $wireTarget }}" />
        @endif
        {{ $slot }}
    </button>
@endif
