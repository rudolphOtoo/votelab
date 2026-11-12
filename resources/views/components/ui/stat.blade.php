@props(['label', 'value', 'hint' => null, 'tone' => null])
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card']) }}>
    <p class="text-sm text-ink-secondary">{{ $label }}</p>
    <p class="mt-1 text-xl font-semibold sm:text-2xl tabular-nums tracking-tight {{ ['warning' => 'text-warning-fg', 'danger' => 'text-danger-fg', 'success' => 'text-success-fg'][$tone] ?? 'text-ink' }}">{{ $value }}</p>
    @if ($hint)<p class="mt-1 text-xs text-ink-secondary">{{ $hint }}</p>@endif
</div>
