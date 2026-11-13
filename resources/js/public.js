// When a public page is restored from the back/forward cache (D-118), the snapshot may be minutes old: a
// category may have closed or had its results hidden since. Re-render every Livewire component at once, so
// the voter sees the current state (and never counts that are now hidden) rather than waiting for a poll.
export function refreshAfterBackForwardCache(Livewire) {
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            Livewire.all().forEach((component) => component.$wire.$refresh());
        }
    });
}
