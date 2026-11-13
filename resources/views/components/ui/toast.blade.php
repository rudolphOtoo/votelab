{{--
  Toast region, once per layout. Announced politely (role=status). Show one with
  $dispatch('toast', { message: 'Saved', tone: 'success' }) from Alpine, $this->dispatch('toast', message: …) from
  Livewire, or session()->flash('toast', ['message' => …, 'tone' => …]).
--}}
@php $flash = session('toast'); @endphp
<div x-data="{
        toasts: [],
        show(detail) {
            const t = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: t.message, tone: t.tone ?? 'success' });
            setTimeout(() => this.toasts = this.toasts.filter(x => x.id !== id), t.duration ?? 5000);
        },
    }"
    x-init="@if (is_array($flash)) show(@js($flash)) @endif"
    x-on:toast.window="show($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[var(--z-toast)] flex flex-col items-center gap-2 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:items-end"
    role="status" aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition.opacity class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[var(--radius-md)] bg-toast px-4 py-3 text-sm text-toast-fg shadow-overlay">
            <span class="mt-0.5 size-2 shrink-0 rounded-full" :class="{ 'bg-toast-success': t.tone === 'success', 'bg-toast-warning': t.tone === 'warning', 'bg-toast-danger': t.tone === 'danger', 'bg-toast-info': t.tone === 'info' }" aria-hidden="true"></span>
            <span class="flex-1" x-text="t.message"></span>
            <button type="button" class="-my-1 -mr-1 inline-flex size-8 items-center justify-center rounded-full text-toast-fg/80 hover:text-toast-fg" x-on:click="toasts = toasts.filter(x => x.id !== t.id)" aria-label="Dismiss"><x-ui.icon name="x" class="size-4" /></button>
        </div>
    </template>
</div>
