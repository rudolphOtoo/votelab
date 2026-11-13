{{--
  Bottom sheet on phones, centred dialog from sm up (side panel with side="right").
  Focus is trapped while open, Esc and the backdrop close it, and focus returns to the trigger.
  Slots: trigger (optional; or open with $dispatch('open-sheet', '<name>')), default (body), footer.
--}}
@props(['name', 'title', 'description' => null, 'side' => 'center', 'open' => false])
@php
    $panel = $side === 'right'
        ? 'sm:inset-y-0 sm:right-0 sm:left-auto sm:h-full sm:max-w-md sm:rounded-none sm:rounded-l-[var(--radius-lg)]'
        : 'sm:inset-auto sm:left-1/2 sm:top-1/2 sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-[var(--radius-lg)]';
@endphp
<div x-data="{ open: @js((bool) $open) }" x-on:open-sheet.window="if ($event.detail === @js($name)) open = true" x-on:close-sheet.window="if ($event.detail === @js($name)) open = false" x-on:keydown.escape.window="open = false" {{ $attributes->only('class') }}>
    @isset($trigger)
        <span x-on:click="open = true">{{ $trigger }}</span>
    @endisset
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[var(--z-sheet)]">
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-black/45" x-on:click="open = false" aria-hidden="true"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $name }}-title" @if ($description) aria-describedby="sheet-{{ $name }}-description" @endif
                 x-show="open" x-trap.noscroll.inert="open"
                 x-transition:enter="transition duration-[var(--duration-slow)] ease-[var(--ease-sheet)]" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition duration-[var(--duration-base)] ease-in" x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0"
                 class="absolute inset-x-0 bottom-0 flex max-h-[92dvh] w-full flex-col rounded-t-[var(--radius-lg)] bg-surface shadow-overlay {{ $panel }}">
                <div class="mx-auto mt-2 h-1.5 w-10 rounded-full bg-hairline sm:hidden" aria-hidden="true"></div>
                <div class="flex items-start justify-between gap-4 px-5 pt-4">
                    <div>
                        <h2 id="sheet-{{ $name }}-title" class="text-lg font-semibold text-ink">{{ $title }}</h2>
                        @if ($description)<p id="sheet-{{ $name }}-description" class="mt-1 text-sm text-ink-secondary">{{ $description }}</p>@endif
                    </div>
                    <button type="button" x-on:click="open = false" class="-mr-2 -mt-1 inline-flex size-11 items-center justify-center rounded-full text-ink-secondary hover:bg-hover" aria-label="Close">
                        <x-ui.icon name="x" />
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 border-t border-hairline px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
