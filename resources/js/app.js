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

async function shrinkImage(file) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') {
        return file;
    }

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(bitmap.width, bitmap.height));

    // Formats most browsers can't display (e.g. iPhone HEIC) are always converted, even when small.
    const isWebFormat = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);

    if (scale === 1 && file.size <= RESIZE_ABOVE_BYTES && isWebFormat) {
        bitmap.close();
        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    // PNG/WebP may be transparent, so keep transparency with WebP; everything else becomes JPEG.
    const type = file.type === 'image/png' || file.type === 'image/webp' ? 'image/webp' : 'image/jpeg';
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.85));

    if (!blob || blob.size >= file.size) {
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
    if (submit) {
        submit.disabled = true;
    }

    try {
        const files = await Promise.all([...input.files].map((file) => shrinkImage(file).catch(() => file)));
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    } finally {
        if (submit) {
            submit.disabled = false;
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
