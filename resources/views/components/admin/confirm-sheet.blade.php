{{--
  A confirmation for consequential actions: a bottom sheet on phones, a centred dialog on wider screens.
  Cancel comes first. The trigger is the default slot; the sheet's body is the "body" slot.
--}}
@props(['title', 'confirm', 'action', 'danger' => false, 'disabled' => false])

<div x-data="{ open: false }" class="inline-block" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true" @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-11 rounded-[var(--radius-md)] px-3 text-sm font-medium disabled:opacity-50 '.($danger ? 'text-danger hover:bg-danger/5' : 'border border-hairline bg-surface text-ink hover:bg-hover')]) }}>
        {{ $slot }}
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" x-on:click.self="open = false">
            <div role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $attributes->get('id', md5($title.$action)) }}" x-trap.noscroll="open"
                 class="w-full max-w-md rounded-t-[var(--radius-lg)] bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-[var(--radius-lg)]">
                <h2 id="sheet-{{ $attributes->get('id', md5($title.$action)) }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
                <div class="mt-2 text-sm text-ink-secondary">{{ $body ?? '' }}</div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="open = false" class="min-h-12 rounded-[var(--radius-md)] border border-hairline px-4 text-sm font-medium text-ink">Cancel</button>
                    <button type="button" x-on:click="open = false; $wire.{{ $action }}" class="min-h-12 rounded-[var(--radius-md)] px-4 text-sm font-semibold {{ $danger ? 'bg-danger text-white' : 'bg-primary text-primary-fg' }}">{{ $confirm }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
