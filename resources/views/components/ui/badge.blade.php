{{-- Small label. tone: neutral | brand | success | warning | danger | info. All pairs ≥ 6:1. --}}
@props(['tone' => 'neutral', 'icon' => null])
@php
    $tones = [
        'neutral' => 'bg-subtle text-ink-secondary',
        'brand' => 'bg-primary-subtle text-primary-text',
        'success' => 'bg-success-bg text-success-fg',
        'warning' => 'bg-warning-bg text-warning-fg',
        'danger' => 'bg-danger-bg text-danger-fg',
        'info' => 'bg-info-bg text-info-fg',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold '.($tones[$tone] ?? $tones['neutral'])]) }}>
    @if ($icon)<x-ui.icon :name="$icon" class="size-3.5" />@endif{{ $slot }}
</span>
