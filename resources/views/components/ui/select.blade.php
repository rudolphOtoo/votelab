@props(['invalid' => false])
<div class="relative">
    <select @if ($invalid) aria-invalid="true" @endif {{ $attributes->merge(['class' => \App\Support\Design\ControlClasses::BASE.' min-h-12 appearance-none pr-10']) }}>{{ $slot }}</select>
    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-ink-secondary" />
</div>
