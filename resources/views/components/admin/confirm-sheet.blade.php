{{--
  Confirmation for consequential actions: a bottom sheet on phones, a centred dialog from 640 px. The body
  says exactly what will happen. Cancel comes first (and is focused first); the confirm button uses the
  danger style for anything destructive or irreversible. Esc and the backdrop cancel.
  The trigger is the default slot; the explanation is the "body" slot.
--}}
@props(['title', 'confirm', 'action', 'danger' => false, 'disabled' => false])

@php $id = 'sheet-'.$attributes->get('id', md5($title.$action)); @endphp

<div x-data="{ open: false }" class="inline-block" x-on:keydown.escape.window="open = false">
    <x-ui.button type="button" size="sm" :variant="$danger ? 'danger-outline' : 'secondary'" x-on:click="open = true" :disabled="$disabled" {{ $attributes->except('id') }}>
        {{ $slot }}
    </x-ui.button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[var(--z-sheet)] flex items-end justify-center sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-black/45" x-on:click="open = false" aria-hidden="true"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="{{ $id }}" aria-describedby="{{ $id }}-body" x-trap.noscroll="open"
                 x-show="open" x-transition:enter="transition duration-[var(--duration-slow)] ease-[var(--ease-sheet)]" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 class="relative w-full max-w-md rounded-t-[var(--radius-lg)] bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-overlay sm:rounded-[var(--radius-lg)] sm:p-6">
                <div class="mx-auto -mt-2 mb-3 h-1.5 w-10 rounded-full bg-hairline sm:hidden" aria-hidden="true"></div>
                <div class="flex items-start gap-3">
                    @if ($danger)
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-danger-bg text-danger-fg"><x-ui.icon name="alert" /></span>
                    @endif
                    <div class="min-w-0">
                        <h2 id="{{ $id }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
                        <div id="{{ $id }}-body" class="mt-1 text-sm text-ink-secondary">{{ $body ?? '' }}</div>
                    </div>
                </div>
                {{ $extra ?? '' }}
                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" x-on:click="open = false" class="sm:order-first">Cancel</x-ui.button>
                    <x-ui.button type="button" :variant="$danger ? 'danger' : 'primary'" x-on:click="open = false; $wire.{{ $action }}">{{ $confirm }}</x-ui.button>
                </div>
            </div>
        </div>
    </template>
</div>
