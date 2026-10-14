// Modals and drawers (native <dialog>) and <details> dropdowns (component doc §4).
// Delegated listeners, so elements added later by Livewire work without re-binding.
const openers = new WeakMap();

export function openDialog(dialog, trigger = document.activeElement) {
    if (!dialog || dialog.open) {
        return;
    }
    openers.set(dialog, trigger);
    dialog.showModal(); // focus moves into the dialog; Esc closes it natively
}

function closeDialog(dialog) {
    if (dialog?.open) {
        dialog.close();
    }
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        event.preventDefault();
        openDialog(document.getElementById(opener.dataset.modalOpen), opener);
        return;
    }

    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        closeDialog(closer.closest('dialog'));
        return;
    }

    // A click on the backdrop (the dialog element itself, outside its content box) closes it.
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        const box = event.target.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
        if (!inside) {
            closeDialog(event.target);
        }
    }

    // Dropdowns: close others, and close this one after an item is chosen or on an outside click.
    document.querySelectorAll('details[data-dropdown][open]').forEach((menu) => {
        const insideMenu = menu.contains(event.target);
        const choseItem = insideMenu && event.target.closest('a, button') && !event.target.closest('summary');
        if (!insideMenu || choseItem) {
            menu.removeAttribute('open');
        }
    });
});

// Focus goes back to whatever opened the dialog (WCAG 2.4.3).
document.addEventListener('close', (event) => {
    if (event.target instanceof HTMLDialogElement) {
        const trigger = openers.get(event.target);
        if (trigger instanceof HTMLElement && document.contains(trigger)) {
            trigger.focus();
        }
    }
}, true);

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }
    const menu = event.target.closest?.('details[data-dropdown][open]') ?? document.querySelector('details[data-dropdown][open]');
    if (menu) {
        menu.removeAttribute('open');
        menu.querySelector('summary')?.focus();
    }
});

// Dialogs rendered with data-open (e.g. a form re-shown with errors) open themselves.
export function openMarkedDialogs(root = document) {
    root.querySelectorAll('dialog[data-open]:not([open])').forEach((dialog) => {
        dialog.removeAttribute('data-open');
        openDialog(dialog, null);
    });
}
