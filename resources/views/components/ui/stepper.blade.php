{{--
  Quantity stepper bound to an Alpine/Livewire number (model: the x-model / wire:model expression).
  Buttons are 48 px, the value is announced, and min/max are enforced.
--}}
@props(['label', 'model', 'min' => 1, 'max' => 10000, 'id' => null])
@php $id ??= 'stepper-'.\Illuminate\Support\Str::random(6); @endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }} x-data="{ get v() { return Number($wire ? $wire.get(@js($model)) : 0) }, set(n) { $wire.set(@js($model), Math.max({{ $min }}, Math.min({{ $max }}, n))) } }">
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    <div class="flex items-stretch gap-2">
        <button type="button" x-on:click="set(v - 1)" :disabled="v <= {{ $min }}" class="inline-flex size-12 items-center justify-center rounded-[var(--radius-md)] border border-strong bg-surface text-ink active:scale-[0.92] disabled:opacity-50" aria-label="One fewer"><x-ui.icon name="minus" /></button>
        <input id="{{ $id }}" type="number" inputmode="numeric" min="{{ $min }}" max="{{ $max }}" wire:model.live.debounce.300ms="{{ $model }}"
               class="min-h-12 w-24 rounded-[var(--radius-field)] border border-strong bg-surface text-center text-lg font-semibold tabular-nums text-ink focus:border-focused focus:outline-none">
        <button type="button" x-on:click="set(v + 1)" :disabled="v >= {{ $max }}" class="inline-flex size-12 items-center justify-center rounded-[var(--radius-md)] border border-strong bg-surface text-ink active:scale-[0.92] disabled:opacity-50" aria-label="One more"><x-ui.icon name="plus" /></button>
    </div>
</div>
