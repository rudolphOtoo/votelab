import defineReactiveMagicProperty from "../utils";

/** here we're we set global utilities to use them in our appx */
document.addEventListener('alpine:init', () => {
    defineReactiveMagicProperty('modal', {
        openModals: new Set(),

        open(id) {

            if (this.openModals.has(id)) return;

            this.openModals.add(id);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id } }));
        },

        close(id) {

            if (!this.openModals.has(id)) return;

            this.openModals.delete(id);
            window.dispatchEvent(new CustomEvent('close-modal', { detail: { id } }));
        },

        closeAll() {
            this.openModals.forEach(id => {
                this.close(id);
            });
        },

        getOpenedModals() {
            return Array.from(Alpine.raw(this.openModals));
        },

        isOpen(id) {
            return this.openModals.has(id);
        }
    })
});
