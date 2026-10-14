// Search filters (components §5.3): progressive enhancement of the GET filter form.
// - Selects and checkboxes marked data-autosubmit apply at once; a place clears the places inside it.
// - Global search: price fields unlock once a currency is chosen (global UX §5).
// - While the next page loads, the results region is marked busy.

function submit(form) {
    document.querySelector('[data-search-results]')?.setAttribute('aria-busy', 'true');
    form.requestSubmit();
}

document.addEventListener('change', (event) => {
    const field = event.target;
    const form = field.closest?.('form[data-search-form]');
    if (!form) {
        return;
    }

    if (field.matches('[data-price-currency]')) {
        form.querySelectorAll('[data-price-field]').forEach((input) => {
            input.disabled = field.value === '';
        });
        return;
    }

    if (field.matches('[data-autosubmit]')) {
        (field.dataset.clears || '').split(',').filter(Boolean).forEach((name) => {
            const inner = form.querySelector(`[name="${name}"]`);
            if (inner) {
                inner.value = '';
            }
        });
        submit(form);
    }
});

document.addEventListener('submit', (event) => {
    if (event.target.matches?.('form[data-search-form]')) {
        document.querySelector('[data-search-results]')?.setAttribute('aria-busy', 'true');
        // Empty fields stay out of the URL, so shared links stay short and readable.
        event.target.querySelectorAll('input, select').forEach((input) => {
            if (input.name && input.value === '' && input.type !== 'checkbox' && !input.disabled) {
                input.disabled = true;
                input.dataset.emptied = '';
            }
        });
    }
});

// Back/forward cache: restore what was changed for the submit.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }
    document.querySelectorAll('[data-emptied]').forEach((input) => {
        input.disabled = false;
        input.removeAttribute('data-emptied');
    });
    document.querySelector('[data-search-results]')?.removeAttribute('aria-busy');
});
