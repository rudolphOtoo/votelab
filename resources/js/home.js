// Platform homepage and site pages: plain JavaScript, no Livewire or Alpine (homepage brief: ≤ 10 KB gzipped).
// Everything here only improves a page that already works without it.

const MINUTE = 60_000;

// "Closes in 2d 4h": the same wording as HomepageDirectionController::closesIn(), kept current every 30 s.
export function closesIn(closesAt, now = Date.now()) {
    const minutes = Math.floor(Math.max(0, closesAt - now) / MINUTE);

    if (minutes < 1) return 'Closing now';
    if (minutes < 60) return `Closes in ${minutes}m`;
    if (minutes < 1440) return `Closes in ${Math.floor(minutes / 60)}h ${minutes % 60}m`;

    return `Closes in ${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
}

export function registerCountdowns(root = document) {
    const items = [...root.querySelectorAll('[data-closes-at]')]
        .map((el) => ({ el, at: Date.parse(el.dataset.closesAt ?? '') }))
        .filter(({ at }) => !Number.isNaN(at));

    if (items.length === 0) return;

    const tick = () => items.forEach(({ el, at }) => { el.textContent = closesIn(at); });
    tick();
    setInterval(tick, 30_000);
}

// The header is transparent over the hero and solid once the page scrolls ([data-site-header], data-scrolled).
export function registerHeader(root = document) {
    const header = root.querySelector('[data-site-header]');
    const hero = root.querySelector('[data-hero]');

    if (!header || !hero || !('IntersectionObserver' in window)) return;

    new IntersectionObserver(([entry]) => {
        header.toggleAttribute('data-scrolled', !entry.isIntersecting);
    }, { rootMargin: `-${header.offsetHeight}px 0px 0px 0px` }).observe(hero);
}

// Sections fade up gently as they enter ([data-reveal]). Content starts hidden only once this script runs (the
// .js-reveal class), so without JavaScript nothing stays invisible; reduced motion turns the effect off.
export function registerReveal(root = document) {
    const sections = [...root.querySelectorAll('[data-reveal]')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (sections.length === 0 || reduced || !('IntersectionObserver' in window)) return;

    document.documentElement.classList.add('js-reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.setAttribute('data-revealed', '');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });
    sections.forEach((section) => observer.observe(section));
}
