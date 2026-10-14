// Form helpers (component doc §2): password show/hide, character counters, error summary focus.

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) {
        return;
    }
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) {
        return;
    }
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? toggle.dataset.labelHide : toggle.dataset.labelShow);
});

document.addEventListener('input', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLTextAreaElement) || !field.dataset.counter) {
        return;
    }
    const counter = document.getElementById(field.dataset.counter);
    if (counter) {
        const max = Number(counter.dataset.max);
        counter.textContent = `${field.value.length} / ${max}`;
        // Announce only near the limit, so screen readers are not flooded on every key.
        counter.setAttribute('aria-live', field.value.length >= max * 0.9 ? 'polite' : 'off');
    }
});

// After a failed submit the error summary takes focus, so keyboard and screen-reader users land on it.
export function focusErrorSummary(root = document) {
    const summary = root.querySelector('[data-error-summary]:not([data-focused])');
    if (summary) {
        summary.setAttribute('data-focused', '');
        summary.focus();
    }
}
