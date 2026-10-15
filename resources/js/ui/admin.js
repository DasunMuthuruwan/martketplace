// Admin work queues (components §5.4 admin.review-pane): single-key shortcuts inside [data-shortcuts].
// J / K open the next / previous [data-queue-item]; other keys click the visible [data-shortcut="key"]
// (A approve, R reject, Esc cancel). Ignored while typing or with modifier keys, so the browser's and
// assistive technology's own shortcuts keep working.

const TYPING = 'input, textarea, select, [contenteditable=""], [contenteditable="true"]';

function visible(element) {
    return element !== null && element.offsetParent !== null && !element.disabled;
}

function moveInQueue(scope, step) {
    const items = [...scope.querySelectorAll('[data-queue-item]')].filter(visible);
    if (items.length === 0) {
        return false;
    }
    const current = items.findIndex((item) => item.getAttribute('aria-current') === 'true');
    const next = current === -1 ? (step > 0 ? 0 : -1) : current + step;
    const target = items[next];
    if (!target) {
        return false;
    }
    target.focus();
    target.click();

    return true;
}

document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) {
        return;
    }
    const scope = document.querySelector('[data-shortcuts]');
    if (!scope || (event.target instanceof Element && event.target.closest(TYPING) && event.key !== 'Escape')) {
        return;
    }

    const key = event.key === 'Escape' ? 'escape' : event.key.toLowerCase();
    let handled = false;
    if (key === 'j' || key === 'k') {
        handled = moveInQueue(scope, key === 'j' ? 1 : -1);
    } else {
        const target = [...scope.querySelectorAll(`[data-shortcut="${CSS.escape(key)}"]`)].find(visible);
        if (target) {
            target.click();
            handled = true;
        }
    }
    if (handled) {
        event.preventDefault();
    }
});

// After a click that makes Livewire render a new part (the reason step), move focus into it once it
// appears: data-focus-after="<selector>".
document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-focus-after]') : null;
    if (!trigger) {
        return;
    }
    const selector = trigger.getAttribute('data-focus-after');
    let tries = 40;
    const attempt = () => {
        const element = document.querySelector(selector);
        if (element) {
            element.focus();
        } else if (tries-- > 0) {
            window.setTimeout(attempt, 50);
        }
    };
    window.setTimeout(attempt, 50);
});
