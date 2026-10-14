// Combobox (ARIA 1.2, component doc §2): type to filter, ↑ ↓ to move, Enter to choose, Esc to close.
// The chosen value goes into the hidden input, which fires input/change for Livewire and forms.

function parts(root) {
    return {
        input: root.querySelector('[data-combobox-input]'),
        value: root.querySelector('[data-combobox-value]'),
        list: root.querySelector('[data-combobox-list]'),
        empty: root.querySelector('[data-combobox-empty]'),
    };
}

function visibleOptions(list) {
    return [...list.querySelectorAll('[role="option"]:not([hidden])')];
}

function setOpen(root, open) {
    const { input, list } = parts(root);
    list.hidden = !open;
    input.setAttribute('aria-expanded', String(open));
    if (!open) {
        input.removeAttribute('aria-activedescendant');
        list.querySelectorAll('[data-active]').forEach((o) => o.removeAttribute('data-active'));
    }
}

function filter(root) {
    const { input, list, empty } = parts(root);
    const term = input.value.trim().toLowerCase();
    let shown = 0;
    list.querySelectorAll('[role="option"]').forEach((option) => {
        const match = term === '' || option.textContent.toLowerCase().includes(term);
        option.hidden = !match;
        shown += match ? 1 : 0;
    });
    empty.hidden = shown > 0;
}

function activate(root, option) {
    const { input, list } = parts(root);
    list.querySelectorAll('[data-active]').forEach((o) => o.removeAttribute('data-active'));
    if (option) {
        option.setAttribute('data-active', '');
        input.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
    }
}

function choose(root, option) {
    const { input, value, list } = parts(root);
    list.querySelectorAll('[aria-selected="true"]').forEach((o) => o.setAttribute('aria-selected', 'false'));
    option.setAttribute('aria-selected', 'true');
    input.value = option.textContent.trim();
    value.value = option.dataset.value;
    value.dispatchEvent(new Event('input', { bubbles: true }));
    value.dispatchEvent(new Event('change', { bubbles: true }));
    setOpen(root, false);
}

document.addEventListener('input', (event) => {
    const root = event.target.closest?.('[data-combobox]');
    if (root && event.target.matches('[data-combobox-input]')) {
        filter(root);
        setOpen(root, true);
        activate(root, visibleOptions(parts(root).list)[0]);
    }
});

document.addEventListener('keydown', (event) => {
    const root = event.target.closest?.('[data-combobox]');
    if (!root || !event.target.matches('[data-combobox-input]')) {
        return;
    }
    const { list } = parts(root);
    const options = visibleOptions(list);
    const current = options.indexOf(list.querySelector('[data-active]'));

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (list.hidden) {
            filter(root);
            setOpen(root, true);
        }
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activate(root, options[Math.min(Math.max(current + step, 0), options.length - 1)]);
    } else if (event.key === 'Enter' && !list.hidden && current >= 0) {
        event.preventDefault();
        choose(root, options[current]);
    } else if (event.key === 'Escape' && !list.hidden) {
        event.preventDefault();
        setOpen(root, false);
    }
});

document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-combobox] [role="option"]');
    if (option) {
        choose(option.closest('[data-combobox]'), option);
        return;
    }
    const input = event.target.closest('[data-combobox-input]');
    document.querySelectorAll('[data-combobox]').forEach((root) => {
        if (input && root.contains(input)) {
            filter(root);
            setOpen(root, true);
        } else {
            setOpen(root, false);
        }
    });
});
