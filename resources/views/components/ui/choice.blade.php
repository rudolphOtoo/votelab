{{-- Checkbox or radio with its label; the whole row is a 44 px tap target. type: checkbox | radio. --}}
@props(['type' => 'checkbox', 'label', 'hint' => null, 'id' => null])
@php $id ??= 'choice-'.\Illuminate\Support\Str::random(6); @endphp
<label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-base text-ink">
    <input id="{{ $id }}" type="{{ $type }}" {{ $attributes->merge(['class' => 'mt-0.5 size-5 shrink-0 accent-[var(--brand)] '.($type === 'radio' ? 'rounded-full' : 'rounded-[4px]')]) }}>
    <span>
        <span class="block">{{ $label }}</span>
        @if ($hint)<span class="block text-sm text-ink-secondary">{{ $hint }}</span>@endif
    </span>
</label>
