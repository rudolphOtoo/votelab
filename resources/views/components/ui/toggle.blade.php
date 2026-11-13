{{-- A switch (role=switch). Bind with x-model / wire:model on the hidden checkbox semantics via Alpine state. --}}
@props(['label', 'checked' => false, 'hint' => null, 'id' => null])
@php $id ??= 'toggle-'.\Illuminate\Support\Str::random(6); @endphp
<div class="flex min-h-11 items-center justify-between gap-4" x-data="{ on: @js((bool) $checked) }">
    <span>
        <span id="{{ $id }}-label" class="block text-base text-ink">{{ $label }}</span>
        @if ($hint)<span class="block text-sm text-ink-secondary">{{ $hint }}</span>@endif
    </span>
    <button type="button" role="switch" :aria-checked="on.toString()" aria-checked="{{ $checked ? 'true' : 'false' }}" aria-labelledby="{{ $id }}-label" x-on:click="on = !on"
            {{ $attributes->merge(['class' => 'relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-[var(--duration-base)]']) }}
            :class="on ? 'bg-primary' : 'bg-strong'">
        <span class="inline-block size-5 rounded-full bg-white shadow-card transition-transform duration-[var(--duration-base)] ease-[var(--ease-out)]" :class="on ? 'translate-x-6' : 'translate-x-1'"></span>
    </button>
</div>
