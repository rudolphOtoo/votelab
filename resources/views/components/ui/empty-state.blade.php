@props(['title', 'icon' => 'inbox'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-[var(--radius-lg)] border border-dashed border-strong/60 bg-surface px-6 py-10 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-primary-subtle text-primary-text"><x-ui.icon :name="$icon" class="size-6" /></span>
    <h3 class="mt-4 text-lg font-semibold text-ink">{{ $title }}</h3>
    @if (trim($slot) !== '')<div class="mt-1 max-w-sm text-sm text-ink-secondary">{{ $slot }}</div>@endif
    @isset($action)<div class="mt-5">{{ $action }}</div>@endisset
</div>
