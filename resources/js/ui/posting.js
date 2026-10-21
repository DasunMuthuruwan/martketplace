// Posting wizard (layout doc §3.4): selects that load the next level reload the step with GET,
// keeping what was entered; the category step filters subcategories as you type.

function reloadWith(form, field) {
    (field.dataset.clears || '').split(',').filter(Boolean).forEach((name) => {
        const inner = form.querySelector(`[name="${name}"]`);
        if (inner) {
            inner.value = '';
        }
    });
    const url = field.dataset.reload || field.dataset.reloadOnChange;
    form.querySelector('input[name="_token"]')?.setAttribute('disabled', '');
    form.method = 'GET';
    form.action = url;
    form.noValidate = true;
    form.submit();
}

document.addEventListener('change', (event) => {
    const form = event.target.closest?.('form[data-posting-form]');
    if (!form) {
        return;
    }
    if (event.target.matches('select[data-reload]')) {
        reloadWith(form, event.target);
        return;
    }
    // City combobox: its hidden value input changes; the visible input carries the reload URL.
    if (event.target.matches('[data-combobox-value]')) {
        const visible = event.target.closest('[data-combobox]')?.querySelector('[data-reload-on-change]');
        if (visible) {
            reloadWith(form, visible);
        }
    }
});

document.addEventListener('input', (event) => {
    if (!event.target.matches?.('[data-category-filter]')) {
        return;
    }
    const box = event.target.closest('[data-category-search]');
    const list = box.querySelector('[data-category-results]');
    const term = event.target.value.trim().toLowerCase();
    let shown = 0;
    list.querySelectorAll('[data-category-name]').forEach((item) => {
        const match = term.length >= 2 && item.dataset.categoryName.includes(term);
        item.hidden = !match;
        shown += match ? 1 : 0;
    });
    list.classList.toggle('hidden', shown === 0);
});

// ---- Details and price steps --------------------------------------------------------------

function syncOther(select) {
    const box = document.getElementById(select.dataset.otherToggle);
    if (box) {
        box.toggleAttribute('data-other-hidden', select.value !== 'other');
    }
}

// Dependent lists (Model under Make): only the chosen parent's group is offered.
function syncDependent(select) {
    const parent = select.form?.querySelector(`[name="${select.dataset.dependsOn}"]`);
    const parentValue = parent?.value ?? '';
    let keep = false;
    select.querySelectorAll('optgroup[data-parent-value]').forEach((group) => {
        const match = parentValue !== '' && group.dataset.parentValue === parentValue;
        group.hidden = !match;
        group.disabled = !match;
        keep ||= match && [...group.options].some((o) => o.selected);
    });
    if (!keep && select.value !== 'other') {
        select.value = '';
    }
}

function syncPrice(form) {
    const chosen = form.querySelector('[data-price-type]:checked')?.value;
    form.querySelector('[data-price-amount]')?.toggleAttribute('data-amount-hidden', !['fixed', 'negotiable'].includes(chosen));
}

document.querySelectorAll('select[data-other-toggle]').forEach(syncOther);
document.querySelectorAll('select[data-depends-on]').forEach(syncDependent);
document.querySelectorAll('form[data-price-form]').forEach(syncPrice);

document.addEventListener('change', (event) => {
    const field = event.target;
    if (field.matches?.('select[data-other-toggle]')) {
        syncOther(field);
    }
    if (field.form) {
        field.form.querySelectorAll(`select[data-depends-on="${field.name}"]`).forEach(syncDependent);
        if (field.matches('[data-price-type]')) {
            syncPrice(field.form);
        }
    }
});

// ---- Photos: upload one per request with a progress bar, then reload to show the result ---------
// One photo per request keeps every request under the server's post_max_size (several phone photos
// together easily exceed it, S-03). Photos that cannot be sent (too large, connection lost) are
// listed after the reload; the server flashes its own outcome for the others.

const CLIENT_ERRORS_KEY = 'photo-client-errors';

document.addEventListener('change', (event) => {
    const input = event.target;
    const form = input.closest?.('form[data-photo-upload]');
    if (form && input.type === 'file' && input.files.length > 0) {
        form.requestSubmit();
    }
});

function sendPhoto(form, file, continuing, onProgress) {
    return new Promise((resolve) => {
        const data = new FormData();
        data.append('_token', form.querySelector('input[name="_token"]')?.value ?? '');
        data.append('photos[]', file);
        if (continuing) {
            data.append('batch_continue', '1');
        }
        const request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                onProgress(e.loaded / e.total);
            }
        });
        // 200/422 carry the server's own per-file messages (flashed for the reload); anything else
        // (413, 5xx, network) means the photo did not get through.
        request.addEventListener('loadend', () => resolve(request.status === 200 || request.status === 422));
        request.send(data);
    });
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-photo-upload]');
    const input = form?.querySelector('input[type="file"]');
    if (!form || !input || input.files.length === 0 || typeof XMLHttpRequest === 'undefined' || typeof Promise === 'undefined') {
        return;
    }
    event.preventDefault();

    const progress = form.querySelector('[data-upload-progress]');
    const bar = form.querySelector('[data-upload-bar]');
    const text = form.querySelector('[data-upload-text]');
    form.querySelector('[data-upload-submit]')?.setAttribute('disabled', '');
    input.setAttribute('disabled', '');
    progress?.classList.remove('hidden');

    const files = Array.from(input.files);
    const maxBytes = Number(form.dataset.maxBytes) || Infinity;
    const label = form.dataset.msgProgress ?? '';
    const failed = [];
    let sent = 0;

    for (const [index, file] of files.entries()) {
        const show = (fraction) => {
            const percent = Math.round(((index + fraction) / files.length) * 100);
            if (bar) {
                bar.style.width = `${percent}%`;
            }
            if (text) {
                text.textContent = `${label.replace(':n', index + 1).replace(':total', files.length)} · ${percent}%`;
            }
        };
        show(0);
        if (file.size > maxBytes) {
            failed.push(`${file.name}: ${form.dataset.msgTooLarge ?? ''}`);
            continue;
        }
        // One at a time, in the chosen order.
        const ok = await sendPhoto(form, file, sent > 0, show);
        if (ok) {
            sent += 1;
        } else {
            failed.push(`${file.name}: ${form.dataset.msgFailed ?? ''}`);
        }
    }

    try {
        if (failed.length > 0) {
            window.sessionStorage.setItem(CLIENT_ERRORS_KEY, JSON.stringify(failed));
        }
    } catch {
        // Storage unavailable: the page still shows what the server accepted.
    }
    window.location.reload();
});

function showClientErrors() {
    const box = document.querySelector('[data-photo-client-errors]');
    if (!box) {
        return;
    }
    let messages = [];
    try {
        messages = JSON.parse(window.sessionStorage.getItem(CLIENT_ERRORS_KEY) ?? '[]');
        window.sessionStorage.removeItem(CLIENT_ERRORS_KEY);
    } catch {
        return;
    }
    if (!Array.isArray(messages) || messages.length === 0) {
        return;
    }
    const list = box.querySelector('[data-photo-client-list]');
    for (const message of messages) {
        const item = document.createElement('li');
        item.textContent = String(message);
        list?.append(item);
    }
    box.classList.remove('hidden');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showClientErrors);
} else {
    showClientErrors();
}
