@props(['status'])

@php
    /** @var \App\Enums\CategoryStatus $status */
    [$label, $classes] = match ($status) {
        \App\Enums\CategoryStatus::Open => ['Voting open', 'bg-success/10 text-success'],
        \App\Enums\CategoryStatus::Embargoed => ['Blind voting', 'bg-warning/10 text-warning'],
        \App\Enums\CategoryStatus::Closed => ['Voting closed', 'bg-subtle text-ink-secondary'],
        \App\Enums\CategoryStatus::Revealed => ['Winner revealed', 'bg-ink text-canvas'],
        \App\Enums\CategoryStatus::Draft => ['Coming soon', 'bg-subtle text-ink-secondary'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $classes]) }}>{{ $label }}</span>
