// Payment result page (cleanup batch 6): plain JavaScript, no Livewire or Alpine. Without JavaScript the page still
// works: "Check now" is a link, and a <noscript> line says to refresh.

// "Copy" buttons: [data-copy="value"], with [data-copy-label] and [data-copy-done] children and a live region.
export function registerCopyButtons(root = document) {
    root.querySelectorAll('[data-copy]').forEach((button) => {
        const label = button.querySelector('[data-copy-label]');
        const idle = label?.textContent ?? '';
        const status = button.parentElement?.querySelector('[data-copy-status]');
        let timer;

        button.addEventListener('click', async () => {
            const value = button.dataset.copy ?? '';

            try {
                await navigator.clipboard.writeText(value);
            } catch {
                const area = document.createElement('textarea');
                area.value = value;
                document.body.append(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }

            button.dataset.copied = 'true';
            if (label) label.textContent = button.dataset.copiedLabel ?? idle;
            if (status) status.textContent = button.dataset.copiedLabel ?? '';
            clearTimeout(timer);
            timer = setTimeout(() => {
                delete button.dataset.copied;
                if (label) label.textContent = idle;
                if (status) status.textContent = '';
            }, 2000);
        });
    });
}

// Pending payments: reload every 10 s to re-check with Paystack (each load verifies, rate-limited per device),
// with a pause/resume button. [data-recheck] holds [data-recheck-running], [data-recheck-seconds],
// [data-recheck-paused] and [data-recheck-toggle].
export function registerRecheck(root = document) {
    const box = root.querySelector('[data-recheck]');

    if (!box) {
        return;
    }

    const running = box.querySelector('[data-recheck-running]');
    const paused = box.querySelector('[data-recheck-paused]');
    const seconds = box.querySelector('[data-recheck-seconds]');
    const toggle = box.querySelector('[data-recheck-toggle]');
    const start = Number(box.dataset.recheck) || 10;
    let left = start;
    let isPaused = false;

    const render = () => {
        running.hidden = isPaused;
        paused.hidden = !isPaused;
        seconds.textContent = String(left);
        toggle.textContent = isPaused ? 'Resume checks' : 'Pause checks';
    };

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        isPaused = !isPaused;
        left = start;
        render();
    });

    setInterval(() => {
        if (!isPaused && --left <= 0) {
            isPaused = true;
            render();
            window.location.reload();

            return;
        }

        render();
    }, 1000);

    render();
}
