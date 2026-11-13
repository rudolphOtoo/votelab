{{--
  The result of an organizer action, shown as a toast (bottom of the screen, announced politely, dismissable,
  fades after 6 s). The text stays in the page, so screen readers and tests always find it. tone: success | danger.
--}}
@props(['message', 'tone' => 'success'])
@if (is_string($message) && $message !== '')
    <div wire:key="notice-{{ md5($message.$tone) }}" x-data="{ shown: true }" x-init="setTimeout(() => shown = false, 6000)"
         class="pointer-events-none fixed inset-x-0 bottom-0 z-[var(--z-toast)] flex justify-center p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:justify-end">
        <div x-show="shown" x-transition.opacity @if ($tone === 'danger') role="alert" @else role="status" aria-live="polite" @endif
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[var(--radius-md)] bg-toast px-4 py-3 text-sm text-toast-fg shadow-overlay">
            <span class="mt-0.5"><x-ui.icon :name="$tone === 'danger' ? 'alert' : 'success'" class="size-4 {{ $tone === 'danger' ? 'text-toast-danger' : 'text-toast-success' }}" /></span>
            <span class="flex-1">{{ $message }}</span>
            <button type="button" x-on:click="shown = false" class="-my-1 -mr-1 inline-flex size-8 items-center justify-center rounded-full text-toast-fg/80 hover:text-toast-fg" aria-label="Dismiss"><x-ui.icon name="x" class="size-4" /></button>
        </div>
    </div>
@endif
