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

// Matches App\Services\PhotoThumbnail::WIDTH.
const THUMBNAIL_WIDTH = 480;

async function makeThumbnail(file) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, THUMBNAIL_WIDTH / bitmap.width);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.8));

    return blob ? new File([blob], 'thumbnail.jpg', { type: 'image/jpeg' }) : null;
}

// Profile photos (<input data-crop>) are framed by the student before upload: the frame has the shape of
// a directory card (4:3, see welcome.blade.php), and round avatars show its middle, so the guide oval marks
// where the face should go. Only the framed part is uploaded.
const CROP_ASPECT = 4 / 3;
const CROP_MAX_WIDTH = 1200;
const CROP_MAX_ZOOM = 4;

function cropPhoto(bitmap) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'm-auto w-[min(100%-2rem,28rem)] rounded-2xl bg-white p-4 sm:p-5 shadow-2xl backdrop:bg-slate-950/70';
        dialog.innerHTML = `
            <h2 class="text-lg font-bold text-slate-900">Position your photo</h2>
            <p class="mt-1 text-sm text-slate-500">Drag to move. Zoom with the slider or pinch. This is how it looks in the alumni directory.</p>
            <div data-crop-frame class="relative mt-3 aspect-[4/3] w-full cursor-grab touch-none select-none overflow-hidden rounded-xl bg-slate-900 active:cursor-grabbing">
                <canvas class="absolute inset-0 h-full w-full"></canvas>
                <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 h-[80%] w-[45%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-2 border-dashed border-white/80"></div>
            </div>
            <label class="mt-4 flex items-center gap-3 text-sm text-slate-600">
                <span aria-hidden="true">➖</span>
                <input data-crop-zoom type="range" min="1" max="${CROP_MAX_ZOOM}" step="0.01" value="1" class="w-full accent-brand-600" aria-label="Zoom">
                <span aria-hidden="true">➕</span>
            </label>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" data-crop-cancel class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" data-crop-done class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Use photo</button>
            </div>`;
        document.body.append(dialog);

        const frame = dialog.querySelector('[data-crop-frame]');
        const canvas = frame.querySelector('canvas');
        const zoomInput = dialog.querySelector('[data-crop-zoom]');
        const context = canvas.getContext('2d');

        // Image position (top-left corner, in frame pixels) and scale; the image always covers the frame.
        let frameWidth = 0;
        let coverScale = 1;
        let zoom = 1;
        let x = 0;
        let y = 0;

        const frameHeight = () => frameWidth / CROP_ASPECT;
        const scale = () => coverScale * zoom;
        const clamp = () => {
            x = Math.min(0, Math.max(frameWidth - bitmap.width * scale(), x));
            y = Math.min(0, Math.max(frameHeight() - bitmap.height * scale(), y));
        };
        const draw = () => {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(frameWidth * ratio);
            canvas.height = Math.round(frameHeight() * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.fillStyle = '#fff';
            context.fillRect(0, 0, frameWidth, frameHeight());
            context.drawImage(bitmap, x, y, bitmap.width * scale(), bitmap.height * scale());
        };
        const layout = () => {
            const previousWidth = frameWidth;
            frameWidth = frame.clientWidth;
            coverScale = Math.max(frameWidth / bitmap.width, frameHeight() / bitmap.height);

            if (previousWidth) {
                x *= frameWidth / previousWidth;
                y *= frameWidth / previousWidth;
            } else {
                // Start centred, a little above the middle where faces usually are.
                x = (frameWidth - bitmap.width * scale()) / 2;
                y = (frameHeight() - bitmap.height * scale()) * 0.3;
            }

            clamp();
            draw();
        };
        // Zoom around a point of the frame (its centre for the slider, the fingers for a pinch).
        const zoomTo = (newZoom, originX = frameWidth / 2, originY = frameHeight() / 2) => {
            newZoom = Math.min(CROP_MAX_ZOOM, Math.max(1, newZoom));
            const factor = newZoom / zoom;
            x = originX - (originX - x) * factor;
            y = originY - (originY - y) * factor;
            zoom = newZoom;
            zoomInput.value = String(zoom);
            clamp();
            draw();
        };

        const pointers = new Map();
        let pinchDistance = 0;
        const distance = () => {
            const [a, b] = [...pointers.values()];
            return Math.hypot(a.x - b.x, a.y - b.y);
        };

        frame.addEventListener('pointerdown', (event) => {
            frame.setPointerCapture(event.pointerId);
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            pinchDistance = pointers.size === 2 ? distance() : 0;
        });
        frame.addEventListener('pointermove', (event) => {
            const previous = pointers.get(event.pointerId);
            if (!previous) {
                return;
            }

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2 && pinchDistance) {
                const box = frame.getBoundingClientRect();
                const [a, b] = [...pointers.values()];
                const newDistance = distance();
                zoomTo(zoom * (newDistance / pinchDistance), (a.x + b.x) / 2 - box.left, (a.y + b.y) / 2 - box.top);
                pinchDistance = newDistance;
            } else if (pointers.size === 1) {
                x += event.clientX - previous.x;
                y += event.clientY - previous.y;
                clamp();
                draw();
            }
        });
        const release = (event) => {
            pointers.delete(event.pointerId);
            pinchDistance = 0;
        };
        frame.addEventListener('pointerup', release);
        frame.addEventListener('pointercancel', release);
        frame.addEventListener('wheel', (event) => {
            event.preventDefault();
            const box = frame.getBoundingClientRect();
            zoomTo(zoom * (event.deltaY < 0 ? 1.1 : 1 / 1.1), event.clientX - box.left, event.clientY - box.top);
        }, { passive: false });
        zoomInput.addEventListener('input', () => zoomTo(Number(zoomInput.value)));
        window.addEventListener('resize', layout);

        const finish = (file) => {
            window.removeEventListener('resize', layout);
            dialog.close();
            dialog.remove();
            resolve(file);
        };

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(null);
        });
        dialog.querySelector('[data-crop-cancel]').addEventListener('click', () => finish(null));
        dialog.querySelector('[data-crop-done]').addEventListener('click', () => {
            // Export the framed part at up to CROP_MAX_WIDTH wide, never larger than the original pixels.
            const sourceWidth = frameWidth / scale();
            const output = document.createElement('canvas');
            output.width = Math.round(Math.min(CROP_MAX_WIDTH, sourceWidth));
            output.height = Math.round(output.width / CROP_ASPECT);
            const outputContext = output.getContext('2d');
            outputContext.fillStyle = '#fff';
            outputContext.fillRect(0, 0, output.width, output.height);
            outputContext.drawImage(bitmap, -x / scale(), -y / scale(), sourceWidth, sourceWidth / CROP_ASPECT, 0, 0, output.width, output.height);
            output.toBlob((blob) => finish(blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }) : null), 'image/jpeg', 0.85);
        });

        dialog.showModal();
        layout();
    });
}

// A small preview of the framed photo under the input, with a button to frame it again.
function showPhotoPreview(input, file) {
    let preview = input.parentElement.querySelector('[data-photo-preview]');

    if (!file) {
        preview?.remove();
        return;
    }

    if (!preview) {
        preview = document.createElement('div');
        preview.dataset.photoPreview = '';
        preview.className = 'mt-2 flex items-center gap-3';
        preview.innerHTML = `
            <img alt="Your photo as it will appear" class="h-18 w-24 rounded-lg border object-cover">
            <button type="button" data-photo-adjust class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Adjust photo</button>`;
        input.after(preview);
        preview.querySelector('[data-photo-adjust]').addEventListener('click', () => preparePhoto(input, input.photoOriginal));
    }

    const image = preview.querySelector('img');
    URL.revokeObjectURL(image.src);
    image.src = URL.createObjectURL(file);
}

async function preparePhoto(input, original) {
    const submit = input.form?.querySelector('button:not([type="button"])');
    const submitLabel = submit?.textContent;
    if (submit) {
        submit.disabled = true;
        submit.textContent = 'Preparing photo…';
    }

    try {
        let files;

        if ('crop' in input.dataset && original) {
            const bitmap = await decodeImage(original).catch(() => null);
            const cropped = bitmap ? await cropPhoto(bitmap) : await shrinkImage(original).catch(() => original);
            bitmap?.close();

            if (!cropped) {
                // Cancelled: keep the photo framed earlier, if any, otherwise nothing is uploaded.
                if (!input.photoCropped) {
                    input.value = '';
                }
                return;
            }

            input.photoCropped = cropped;
            files = [cropped];
            showPhotoPreview(input, cropped);
        } else {
            files = await Promise.all([...input.files].map((file) => shrinkImage(file).catch(() => file)));
        }

        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;

        // Profile photos also get a small copy for directory cards and avatars (sent as e.g. photo_thumbnail);
        // Vercel's PHP can't resize images, so the browser makes it.
        const thumbnailInput = input.dataset.thumbnail && input.form?.querySelector(`input[name="${input.dataset.thumbnail}"]`);
        if (thumbnailInput) {
            const thumbnail = await makeThumbnail(files[0]).catch(() => null);
            const thumbnailTransfer = new DataTransfer();
            if (thumbnail) {
                thumbnailTransfer.items.add(thumbnail);
            }
            thumbnailInput.files = thumbnailTransfer.files;
        }
    } finally {
        if (submit) {
            submit.disabled = false;
            submit.textContent = submitLabel;
        }
    }
}

document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.accept.startsWith('image') || !input.files?.length) {
        return;
    }

    // A new choice replaces the earlier one; the original is kept so "Adjust photo" can frame it again.
    input.photoOriginal = input.files[0];
    input.photoCropped = null;
    preparePhoto(input, input.photoOriginal);
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

// Alumni directory: every card shows a shimmer until all photos on the page have loaded, then they appear
// together instead of popping in one by one. A slow photo can't hold the page back for more than 6 seconds.
const PHOTO_REVEAL_TIMEOUT_MS = 6000;

function revealDirectoryPhotosTogether(root = document) {
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
