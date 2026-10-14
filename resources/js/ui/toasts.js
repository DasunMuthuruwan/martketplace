// Toasts (component doc §4): success/info disappear after 5 s, errors stay until closed.
const AUTO_DISMISS_MS = 5000;

function region() {
    return document.querySelector('[data-toasts]');
}

function schedule(toast) {
    if (toast.dataset.variant !== 'error') {
        setTimeout(() => toast.remove(), AUTO_DISMISS_MS);
    }
}

export function showToast(message, variant = 'success') {
    const host = region();
    const template = host?.querySelector('[data-toast-template]');
    if (!host || !template || !message) {
        return;
    }
    const toast = template.content.firstElementChild.cloneNode(true);
    const kind = ['success', 'error', 'info'].includes(variant) ? variant : 'info';
    toast.dataset.variant = kind;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    toast.querySelector('[data-toast-message]').textContent = message;
    const icon = host.querySelector(`[data-toast-icon-${kind}]`);
    if (icon) {
        toast.querySelector('[data-toast-icon]').replaceWith(icon.content.firstElementChild.cloneNode(true));
    }
    host.insertBefore(toast, template);
    schedule(toast);
}

document.addEventListener('click', (event) => {
    event.target.closest('[data-toast-close]')?.closest('[data-toast]')?.remove();
});

// Livewire: $this->dispatch('toast', message: '…', variant: 'success')
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    showToast(detail?.message, detail?.variant);
});

export function initServerToasts(root = document) {
    root.querySelectorAll('[data-toasts] > [data-toast]:not([data-scheduled])').forEach((toast) => {
        toast.setAttribute('data-scheduled', '');
        schedule(toast);
    });
}
