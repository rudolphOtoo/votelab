@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'primary' => 'bg-primary text-primary-fg shadow-card hover:bg-primary-hover',
        'secondary' => 'border border-strong bg-surface text-ink hover:bg-hover',
        'danger' => 'bg-danger text-white shadow-card hover:bg-danger-fg',
    };
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-[var(--radius-md)] px-4 text-base font-semibold transition-[transform,background-color] duration-[var(--duration-fast)] active:scale-[0.98] disabled:opacity-55 {$classes}"]) }}>
    {{ $slot }}
</button>
