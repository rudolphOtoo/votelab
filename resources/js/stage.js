// Stage screen behaviour: keep the projector awake, never show Livewire errors to the audience (a failed or
// throttled poll just keeps the last good screen), and play the winner reveal once per category.
import confetti from 'canvas-confetti';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealed = new Set();

export function registerStage(Alpine, Livewire) {
    Livewire.hook('request', ({ fail }) => {
        fail(({ preventDefault }) => {
            if (document.querySelector('[data-stage-screen]')) {
                preventDefault();
            }
        });
    });

    Alpine.data('stageScreen', () => ({
        lock: null,

        async init() {
            await this.keepAwake();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.keepAwake();
            });
        },

        async keepAwake() {
            if (!('wakeLock' in navigator) || (this.lock && !this.lock.released)) return;
            try {
                this.lock = await navigator.wakeLock.request('screen');
            } catch {
                // Not allowed (e.g. battery saver): the screen may sleep; nothing to show the audience.
            }
        },
    }));

    // The reveal plays once per category per screen: "And the winner is…", a pause, then the podium and three
    // confetti bursts in the organization's colour. Under prefers-reduced-motion the podium shows at once, with
    // no confetti; after the first showing (or a re-render on poll) it stays on the podium.
    Alpine.data('stageReveal', (categoryId, accent) => ({
        phase: revealed.has(categoryId) || reducedMotion() ? 'show' : 'intro',

        init() {
            if (this.phase === 'show') return;
            revealed.add(categoryId);
            setTimeout(() => {
                this.phase = 'show';
                const canvas = document.querySelector('[data-stage-confetti]');
                const fire = canvas ? confetti.create(canvas, { resize: true, disableForReducedMotion: true }) : confetti;
                const colors = [accent, '#FFFFFF', accent, '#F5F5F4'];
                if (canvas) canvas.dataset.fired = 'true';
                [0, 350, 700].forEach((delay, i) => {
                    setTimeout(() => fire({ particleCount: 140, spread: 90, startVelocity: 55, origin: { x: [0.2, 0.8, 0.5][i], y: 0.6 }, colors, disableForReducedMotion: true }), delay);
                });
            }, 1800);
        },
    }));
}
