import { initServerToasts } from './ui/toasts.js';
import { focusErrorSummary } from './ui/forms.js';
import { openMarkedDialogs } from './ui/overlays.js';
import './ui/combobox.js';
import './ui/search.js';
import './ui/suggest.js';
import './ui/listing.js';
import './ui/posting.js';
import './ui/admin.js';

// Theme choice (design system §9): buttons with data-theme-choice="light|dark|system" switch the
// theme; the choice is remembered per browser. partials/theme-script applies it before first paint.
const media = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(choice) {
    const dark = choice === 'dark' || (choice === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.theme = choice;
    document.querySelectorAll('[data-theme-choice]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.themeChoice === choice));
    });
}

function storedTheme() {
    try {
        return localStorage.getItem('theme') || 'system';
    } catch {
        return 'system';
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-choice]');
    if (!button) {
        return;
    }
    const choice = button.dataset.themeChoice;
    try {
        localStorage.setItem('theme', choice);
    } catch {
        // Private mode or blocked storage: the choice lasts for this page only.
    }
    applyTheme(choice);
});

media.addEventListener('change', () => {
    if (storedTheme() === 'system') {
        applyTheme('system');
    }
});

// Things rendered by the server that need a nudge: on load, and after Livewire updates the page.
function enhance() {
    applyTheme(storedTheme());
    initServerToasts();
    openMarkedDialogs();
    focusErrorSummary();
}

enhance();
new MutationObserver(() => {
    initServerToasts();
    openMarkedDialogs();
    focusErrorSummary();
}).observe(document.body, { childList: true, subtree: true });
