@props(['name', 'src' => null, 'size' => 'md'])
@php
    $dim = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-base'][$size] ?? 'size-10 text-sm';
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->merge(['class' => "{$dim} rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "{$dim} inline-flex items-center justify-center rounded-full bg-primary-subtle font-semibold text-primary-text"]) }} aria-hidden="true">{{ $initials }}</span>
@endif
