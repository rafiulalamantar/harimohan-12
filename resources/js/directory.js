import { OFFLINE_MESSAGE, showNetworkMessage } from './network-status';

// Alumni directory pages (welcome page #alumni) swap in place instead of reloading the whole page,
// which showed the header first and then scrolled down. Falls back to a normal page load on any error.
let directoryPageSwapped = false;

async function showDirectoryPage(url, { push }) {
    const response = await fetch(url, { headers: { Accept: 'text/html' } });
    const next = response.ok
        ? new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('alumni')
        : null;
    const current = document.getElementById('alumni');

    if (!next || !current) {
        throw new Error('Directory section not found');
    }

    current.replaceWith(next);
    revealDirectoryPhotosTogether(next);

    if (push) {
        history.pushState({ directoryPage: true }, '', url);
        directoryPageSwapped = true;
    }

    next.scrollIntoView({ block: 'start' });
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('[data-directory-pages] a[href]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();

    if (!navigator.onLine) {
        showNetworkMessage(OFFLINE_MESSAGE, 'offline');

        return;
    }

    showDirectoryPage(link.href, { push: true }).catch(() => {
        window.location.href = link.href;
    });
});

window.addEventListener('popstate', () => {
    // Only Back/Forward between pages this script swapped; other history entries keep the browser default.
    if (directoryPageSwapped && document.getElementById('alumni')) {
        showDirectoryPage(window.location.href, { push: false }).catch(() => window.location.reload());
    }
});

// Alumni directory: every card shows a shimmer until all photos on the page have loaded, then they appear
// together instead of popping in one by one. A slow photo can't hold the page back for more than 6 seconds.
const PHOTO_REVEAL_TIMEOUT_MS = 6000;

export function revealDirectoryPhotosTogether(root = document) {
    const frames = [...root.querySelectorAll('[data-photo-frame]')];
    const reveal = () => frames.forEach((frame) => frame.classList.add('is-loaded'));
    const images = frames.map((frame) => frame.querySelector('img')).filter(Boolean);
    const pending = images.filter((image) => !image.complete);

    // Already failed before this ran: show the card background rather than a broken-image icon.
    images.filter((image) => image.complete && image.naturalWidth === 0).forEach((image) => {
        image.style.visibility = 'hidden';
    });

    if (pending.length === 0) {
        reveal();

        return;
    }

    let remaining = pending.length;
    const settle = (event) => {
        if (event.type === 'error') {
            event.target.style.visibility = 'hidden';
        }

        remaining -= 1;
        if (remaining === 0) {
            reveal();
        }
    };

    pending.forEach((image) => {
        image.addEventListener('load', settle, { once: true });
        image.addEventListener('error', settle, { once: true });
    });
    setTimeout(reveal, PHOTO_REVEAL_TIMEOUT_MS);
}

revealDirectoryPhotosTogether();
