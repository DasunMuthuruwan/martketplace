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
