@props(['value', 'max' => 100, 'label'])
@php $percent = $max > 0 ? max(0, min(100, round($value / $max * 100))) : 0; @endphp
<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3 text-sm"><span class="font-medium text-ink">{{ $label }}</span><span class="tabular-nums text-ink-secondary">{{ $value }} of {{ $max }}</span></div>
    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}">
        <div class="h-full origin-left rounded-full bg-primary transition-transform duration-[var(--duration-slow)] ease-[var(--ease-out)]" style="transform: scaleX({{ $percent / 100 }})"></div>
    </div>
</div>
