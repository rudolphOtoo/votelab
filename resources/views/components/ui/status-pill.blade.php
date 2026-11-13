{{-- A category's status in voter language (never "embargo"). --}}
@props(['status'])
@php
    /** @var \App\Enums\CategoryStatus $status */
    [$label, $tone, $icon] = match ($status) {
        \App\Enums\CategoryStatus::Open => ['Voting open', 'success', null],
        \App\Enums\CategoryStatus::Embargoed => ['Voting open · results hidden', 'warning', 'lock'],
        \App\Enums\CategoryStatus::Closed => ['Voting closed', 'neutral', null],
        \App\Enums\CategoryStatus::Revealed => ['Winner announced', 'brand', null],
        \App\Enums\CategoryStatus::Draft => ['Coming soon', 'neutral', null],
    };
@endphp
<x-ui.badge :tone="$tone" :icon="$icon" {{ $attributes }}>{{ $label }}</x-ui.badge>
