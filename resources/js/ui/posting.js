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
