@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'primary' => 'bg-primary text-primary-fg',
        'secondary' => 'border border-hairline bg-surface text-ink hover:bg-hover',
        'danger' => 'border border-danger/40 bg-surface text-danger hover:bg-danger/5',
    };
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => "min-h-12 w-full rounded-[var(--radius-md)] px-4 text-base font-semibold transition-transform active:scale-[0.98] disabled:opacity-60 {$classes}"]) }}>
    {{ $slot }}
</button>
