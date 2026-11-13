@props(['name', 'src' => null, 'size' => 'md'])
@php
    $dim = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-base'][$size] ?? 'size-10 text-sm';
    $px = ['sm' => 32, 'md' => 40, 'lg' => 56][$size] ?? 40;
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
@if ($src)
    <img src="{{ $src }}" alt="" width="{{ $px }}" height="{{ $px }}" loading="lazy" decoding="async" {{ $attributes->merge(['class' => "{$dim} shrink-0 rounded-full bg-subtle object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "{$dim} inline-flex shrink-0 items-center justify-center rounded-full bg-primary-subtle font-semibold text-primary-text"]) }} aria-hidden="true">{{ $initials }}</span>
@endif
