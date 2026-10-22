// Live search suggestions (G1, ADR-027): an input with data-suggest-url becomes an accessible
// combobox (ARIA 1.2 pattern). After a short pause it asks the server for categories, makes and
// models, places and popular searches; arrow keys move, Enter opens the highlighted suggestion (or
// submits the search as typed), Escape closes. Text only (textContent): suggestions never inject markup.

const DELAY = 200;
const MIN_LENGTH = 2;

function setUp(input) {
    const form = input.closest('form');
    if (!form || input.dataset.suggestReady) {
        return;
    }
    input.dataset.suggestReady = '1';
    const types = JSON.parse(input.dataset.suggestTypes || '{}');
    const id = `${input.id || 'search'}-suggestions`;

    form.classList.add('relative');
    const list = document.createElement('ul');
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', input.dataset.suggestLabel || '');
    list.className = 'absolute left-0 right-0 top-full z-40 mt-1 hidden max-h-80 overflow-y-auto rounded-card border border-line bg-surface py-1 shadow-lg';
    const status = document.createElement('span');
    status.className = 'sr-only';
    status.setAttribute('aria-live', 'polite');
    form.append(list, status);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id);
    input.setAttribute('autocomplete', 'off');

    let items = [];
    let active = -1;
    let timer = null;
    let controller = null;

    const close = () => {
        list.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const highlight = (index) => {
        const options = list.querySelectorAll('[role="option"]');
        options.forEach((option, i) => {
            const on = i === index;
            option.setAttribute('aria-selected', String(on));
            option.classList.toggle('bg-surface-hover', on);
        });
        active = index;
        if (index >= 0 && options[index]) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const render = () => {
        list.replaceChildren();
        items.forEach((item, index) => {
            const option = document.createElement('li');
            option.id = `${id}-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.className = 'flex cursor-pointer items-baseline justify-between gap-3 px-3 py-2 text-ui text-fg';
            const label = document.createElement('span');
            label.className = 'min-w-0 truncate';
            label.textContent = item.label;
            const type = document.createElement('span');
            type.className = 'shrink-0 text-small text-fg-subtle';
            type.textContent = types[item.type] || '';
            option.append(label, type);
            option.addEventListener('mousedown', (event) => event.preventDefault()); // keep focus in the input
            option.addEventListener('click', () => { window.location.assign(item.url); });
            option.addEventListener('mousemove', () => highlight(index));
            list.append(option);
        });
        const open = items.length > 0;
        list.classList.toggle('hidden', !open);
        input.setAttribute('aria-expanded', String(open));
        status.textContent = open ? (input.dataset.suggestCount || '').replace(':count', items.length) : '';
        active = -1;
    };

    const load = async () => {
        const text = input.value.trim();
        if (text.length < MIN_LENGTH) {
            items = [];
            render();
            return;
        }
        controller?.abort();
        controller = new AbortController();
        const url = new URL(input.dataset.suggestUrl, window.location.origin);
        url.searchParams.set('q', text);
        if (input.dataset.suggestCc) {
            url.searchParams.set('cc', input.dataset.suggestCc);
        }
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            items = Array.isArray(data.items) ? data.items : [];
            render();
        } catch {
            // Aborted or offline: the search box keeps working without suggestions.
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, DELAY);
    });
    input.addEventListener('keydown', (event) => {
        const open = !list.classList.contains('hidden');
        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            if (!open) render();
            highlight((active + 1) % items.length);
        } else if (event.key === 'ArrowUp' && open) {
            event.preventDefault();
            highlight(active <= 0 ? items.length - 1 : active - 1);
        } else if (event.key === 'Enter' && open && active >= 0) {
            event.preventDefault();
            window.location.assign(items[active].url);
        } else if (event.key === 'Escape' && open) {
            event.preventDefault();
            close();
        }
    });
    input.addEventListener('blur', () => setTimeout(close, 100));
}

function init() {
    document.querySelectorAll('input[data-suggest-url]').forEach(setUp);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
