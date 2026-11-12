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

    Alpine.data('stageReveal', (categoryId) => ({
        init() {
            if (revealed.has(categoryId) || reducedMotion()) return;
            revealed.add(categoryId);
            const gold = ['#FBBF24', '#F59E0B', '#FDE68A', '#FFFFFF'];
            [0, 350, 700].forEach((delay, i) => {
                setTimeout(() => confetti({ particleCount: 140, spread: 90, startVelocity: 55, origin: { x: [0.2, 0.8, 0.5][i], y: 0.6 }, colors: gold, disableForReducedMotion: true }), delay);
            });
        },
    }));
}
