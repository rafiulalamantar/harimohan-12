// Ask before destructive form submits: <form data-confirm="Delete this?">.
// Kept out of inline onsubmit="" handlers so the Content-Security-Policy can forbid inline scripts.
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Shrink large photos in the browser before upload. Vercel rejects request bodies over 4.5 MB
// (413 FUNCTION_PAYLOAD_TOO_LARGE) before Laravel sees them, and phone cameras easily exceed that.
const MAX_IMAGE_EDGE = 1600;
const RESIZE_ABOVE_BYTES = 1024 * 1024;

// iPhone HEIC photos: only Safari can display them, so they are always converted to JPEG.
const isHeic = (file) => ['image/heic', 'image/heif'].includes(file.type) || /\.hei[cf]$/i.test(file.name);

async function decodeImage(file) {
    if (isHeic(file)) {
        // heic-to decodes HEIC with WebAssembly. Its ~3 MB code is only downloaded when someone picks a HEIC photo;
        // the CSP build avoids 'unsafe-eval' (see AddSecurityHeaders for the wasm/worker allowances it needs).
        const { heicTo } = await import('heic-to/csp');

        return heicTo({ blob: file, type: 'bitmap' });
    }

    return createImageBitmap(file, { imageOrientation: 'from-image' });
}

async function shrinkImage(file) {
    const heic = isHeic(file);

    if (!heic && (!file.type.startsWith('image/') || file.type === 'image/gif')) {
        return file;
    }

    const bitmap = await decodeImage(file);
    const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(bitmap.width, bitmap.height));

    // Formats most browsers can't display (e.g. iPhone HEIC) are always converted, even when small.
    const isWebFormat = !heic && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);

    if (scale === 1 && file.size <= RESIZE_ABOVE_BYTES && isWebFormat) {
        bitmap.close();
        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    // PNG/WebP may be transparent, so keep transparency with WebP; everything else (HEIC included) becomes JPEG.
    const type = file.type === 'image/png' || file.type === 'image/webp' ? 'image/webp' : 'image/jpeg';
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.85));

    if (!blob || (isWebFormat && blob.size >= file.size)) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + (type === 'image/webp' ? '.webp' : '.jpg');

    return new File([blob], name, { type, lastModified: Date.now() });
}

document.addEventListener('change', async (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.accept.startsWith('image') || !input.files?.length) {
        return;
    }

    const submit = input.form?.querySelector('button:not([type="button"])');
    const submitLabel = submit?.textContent;
    if (submit) {
        submit.disabled = true;
        submit.textContent = 'Preparing photo…';
    }

    try {
        const files = await Promise.all([...input.files].map((file) => shrinkImage(file).catch(() => file)));
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    } finally {
        if (submit) {
            submit.disabled = false;
            submit.textContent = submitLabel;
        }
    }
});

// Header dropdowns (<details data-dropdown>: the ☰ menu and the admin 🔔 bell) behave like menus:
// opening one closes the other so they never overlap, and a tap outside or Escape closes them.
const closeDropdowns = (except = null) => {
    document.querySelectorAll('details[data-dropdown][open]').forEach((dropdown) => {
        if (dropdown !== except) {
            dropdown.open = false;
        }
    });
};

document.addEventListener('toggle', (event) => {
    if (event.target.matches?.('details[data-dropdown]') && event.target.open) {
        closeDropdowns(event.target);
    }
}, true);

document.addEventListener('click', (event) => {
    closeDropdowns(event.target.closest('details[data-dropdown]'));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeDropdowns();
    }
});

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

// Connection notices: a bar at the bottom while offline, a short "back online" when it returns, and forms
// are held back while offline so nothing is lost to the browser's error page. public/sw.js serves
// public/offline.html when a whole page can't load.
const OFFLINE_MESSAGE = "📡 You're offline. Check your Wi-Fi or mobile data. We'll let you know when you're back.";
const networkStatus = document.querySelector('[data-network-status]');
const networkStatusBaseClass = networkStatus?.className ?? '';
const networkTones = {
    offline: 'bg-slate-900 text-white',
    online: 'bg-emerald-600 text-white',
};
let networkStatusTimer;

function showNetworkMessage(message, tone) {
    if (!networkStatus) {
        return;
    }

    clearTimeout(networkStatusTimer);
    networkStatus.textContent = message;
    networkStatus.className = `${networkStatusBaseClass} ${networkTones[tone]}`;
    networkStatus.hidden = false;

    if (tone === 'online') {
        networkStatusTimer = setTimeout(() => {
            networkStatus.hidden = true;
        }, 3000);
    }
}

window.addEventListener('offline', () => showNetworkMessage(OFFLINE_MESSAGE, 'offline'));
window.addEventListener('online', () => showNetworkMessage("✅ You're back online.", 'online'));

if (!navigator.onLine) {
    showNetworkMessage(OFFLINE_MESSAGE, 'offline');
}

// Capture phase, so an offline submit is stopped before any "Are you sure?" prompt.
document.addEventListener('submit', (event) => {
    if (!navigator.onLine) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showNetworkMessage("📡 You're offline, so nothing was sent. What you typed is still here. Try again once you're connected.", 'offline');
    }
}, true);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
