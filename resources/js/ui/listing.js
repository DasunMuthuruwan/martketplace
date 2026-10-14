// Listing page (components §5.2): gallery, description clamp, contact reveal, save, share, report.
import { openDialog } from './overlays.js';
import { showToast } from './toasts.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });
    let data = {};
    try {
        data = await response.json();
    } catch {
        // Empty or non-JSON body: status alone decides.
    }
    return { status: response.status, data };
}

// ---- Gallery ------------------------------------------------------------------------------

function galleryParts(root) {
    const track = root.querySelector('[data-gallery-track]');
    return { track, slides: [...root.querySelectorAll('[data-gallery-slide]')], thumbs: [...root.querySelectorAll('[data-gallery-thumb]')] };
}

function currentIndex(track) {
    return Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));
}

function goTo(root, index) {
    const { track, slides } = galleryParts(root);
    const i = Math.max(0, Math.min(index, slides.length - 1));
    track.scrollTo({ left: i * track.clientWidth, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}

function syncGallery(root) {
    const { track, slides, thumbs } = galleryParts(root);
    const i = currentIndex(track);
    const counter = root.querySelector('[data-gallery-counter]');
    if (counter) {
        counter.textContent = `${i + 1} / ${slides.length}`;
    }
    thumbs.forEach((thumb, n) => (n === i ? thumb.setAttribute('aria-current', 'true') : thumb.removeAttribute('aria-current')));
}

document.querySelectorAll('[data-gallery]').forEach((root) => {
    const { track } = galleryParts(root);
    let timer;
    track?.addEventListener('scroll', () => {
        clearTimeout(timer);
        timer = setTimeout(() => syncGallery(root), 60);
    });
    track?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(root, currentIndex(track) + (event.key === 'ArrowRight' ? 1 : -1));
        }
    });
});

document.addEventListener('click', (event) => {
    const root = event.target.closest('[data-gallery]');
    if (!root) {
        return;
    }
    const { track } = galleryParts(root);
    if (event.target.closest('[data-gallery-prev]')) {
        goTo(root, currentIndex(track) - 1);
    } else if (event.target.closest('[data-gallery-next]')) {
        goTo(root, currentIndex(track) + 1);
    } else if (event.target.closest('[data-gallery-thumb]')) {
        goTo(root, Number(event.target.closest('[data-gallery-thumb]').dataset.galleryThumb));
    } else if (event.target.closest('[data-gallery-zoom]')) {
        const button = event.target.closest('[data-gallery-zoom]');
        const dialog = root.querySelector('#gallery-zoom');
        const image = dialog?.querySelector('[data-gallery-zoom-image]');
        if (dialog && image) {
            image.src = button.dataset.galleryZoom;
            image.alt = button.querySelector('img')?.alt ?? '';
            openDialog(dialog, button);
        }
    }
});

// ---- Description: clamp long text with "Show more" --------------------------------------------

document.querySelectorAll('[data-clamp]').forEach((text) => {
    const toggle = text.parentElement.querySelector('[data-clamp-toggle]');
    text.classList.add('line-clamp-[12]');
    if (text.scrollHeight > text.clientHeight + 4 && toggle) {
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const expanded = text.classList.toggle('line-clamp-[12]') === false;
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.textContent = expanded ? toggle.dataset.labelLess : toggle.dataset.labelMore;
        });
    } else {
        text.classList.remove('line-clamp-[12]');
    }
});

// ---- Contact reveal (MSG-04): JSON, numbers never in the HTML ---------------------------------

function contactBox(button) {
    return document.getElementById(button.dataset.contactTarget) ?? button.closest('[data-contact-box]');
}

function showContactMessage(box, text, link = null) {
    const result = box.querySelector('[data-contact-result]');
    result.replaceChildren();
    const p = document.createElement('p');
    p.className = 'text-small text-fg-muted';
    p.textContent = text;
    if (link) {
        const a = document.createElement('a');
        a.href = link.href;
        a.textContent = link.text;
        a.className = 'ml-1 font-medium text-fg underline underline-offset-2';
        p.append(a);
    }
    result.append(p);
}

async function reveal(button) {
    const box = contactBox(button);
    const messages = box?.querySelector('[data-contact-messages]')?.dataset;
    if (!box || !messages) {
        return;
    }
    const channel = button.dataset.contactChannel;
    // A popup must open in the click itself; it is pointed at WhatsApp once the link arrives.
    const popup = channel === 'whatsapp' ? window.open('', '_blank') : null;
    const token = box.querySelector('[name="cf-turnstile-response"]')?.value;

    button.setAttribute('aria-busy', 'true');
    const { status, data } = await postJson(button.dataset.contactUrl, { channel, turnstile_token: token || null });
    button.removeAttribute('aria-busy');

    if (status === 200 && data.channel === 'call') {
        const result = box.querySelector('[data-contact-result]');
        result.replaceChildren();
        const link = document.createElement('a');
        link.href = `tel:${data.phone}`;
        link.className = 'flex h-11 items-center justify-center gap-2 rounded-control border border-control bg-surface text-price font-bold tabular text-fg hover:bg-surface-hover';
        link.textContent = data.display;
        link.setAttribute('aria-label', `${messages.callLabel} ${data.display}`);
        result.append(link);
        box.querySelector('[data-contact-challenge]')?.setAttribute('hidden', '');
        return;
    }
    if (status === 200 && data.channel === 'whatsapp') {
        if (popup) {
            popup.opener = null;
            popup.location.href = data.url;
        } else {
            window.location.href = data.url;
        }
        showContactMessage(box, messages.whatsapp);
        return;
    }

    popup?.close();
    if (status === 401) {
        showContactMessage(box, '', { href: messages.loginUrl, text: messages.login });
    } else if (status === 428) {
        box.querySelector('[data-contact-challenge]')?.removeAttribute('hidden');
    } else if (status === 429) {
        showContactMessage(box, messages.limit);
    } else if (status === 403 || status === 404) {
        showContactMessage(box, messages.unavailable);
    } else {
        showContactMessage(box, messages.error);
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-contact-channel]');
    if (button) {
        event.preventDefault();
        reveal(button);
    }
});

// ---- Save (favourite) -----------------------------------------------------------------------

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-favourite-url]');
    if (!button) {
        return;
    }
    event.preventDefault();
    const { status, data } = await postJson(button.dataset.favouriteUrl, {});
    if (status !== 200) {
        showToast(document.querySelector('[data-contact-messages]')?.dataset.error ?? '', 'error');
        return;
    }
    document.querySelectorAll(`[data-favourite-url="${button.dataset.favouriteUrl}"]`).forEach((b) => {
        b.setAttribute('aria-pressed', String(data.favourited));
        const label = b.querySelector('[data-favourite-label]');
        if (label) {
            label.textContent = data.favourited ? b.dataset.labelOn : b.dataset.labelOff;
        }
    });
    showToast(data.favourited ? button.dataset.labelOn : button.dataset.labelOff, 'success');
});

// ---- Share --------------------------------------------------------------------------------

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-share-url]');
    if (!button) {
        return;
    }
    const url = button.dataset.shareUrl;
    if (navigator.share) {
        try {
            await navigator.share({ title: button.dataset.shareTitle, url });
            return;
        } catch {
            // Cancelled: fall back to copying.
        }
    }
    try {
        await navigator.clipboard.writeText(url);
        showToast(button.dataset.copied, 'success');
    } catch {
        window.prompt('', url);
    }
});

// ---- Report ------------------------------------------------------------------------------

document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-report-form]');
    if (!form) {
        return;
    }
    event.preventDefault();
    const error = form.querySelector('[data-report-error]');
    error.hidden = true;
    const body = Object.fromEntries(new FormData(form).entries());
    const { status, data } = await postJson(form.action, body);

    if (status === 201) {
        form.closest('dialog')?.close();
        form.reset();
        showToast(data.message, 'success');
        return;
    }
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : data.message;
    error.textContent = first || form.dataset.error;
    error.hidden = false;
    window.turnstile?.reset?.();
});
