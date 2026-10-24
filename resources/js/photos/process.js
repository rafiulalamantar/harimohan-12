// Photo helpers shared by the upload form and the admin re-frame button: decode (HEIC included),
// shrink before upload, and make the small card thumbnail.

// Shrink large photos in the browser before upload. Vercel rejects request bodies over 4.5 MB
// (413 FUNCTION_PAYLOAD_TOO_LARGE) before Laravel sees them, and phone cameras easily exceed that.
const MAX_IMAGE_EDGE = 1600;
const RESIZE_ABOVE_BYTES = 1024 * 1024;

// iPhone HEIC photos: only Safari can display them, so they are always converted to JPEG.
const isHeic = (file) => ['image/heic', 'image/heif'].includes(file.type) || /\.hei[cf]$/i.test(file.name);

export async function decodeImage(file) {
    if (isHeic(file)) {
        // heic-to decodes HEIC with WebAssembly. Its ~3 MB code is only downloaded when someone picks a HEIC photo;
        // the CSP build avoids 'unsafe-eval' (see AddSecurityHeaders for the wasm/worker allowances it needs).
        const { heicTo } = await import('heic-to/csp');

        return heicTo({ blob: file, type: 'bitmap' });
    }

    return createImageBitmap(file, { imageOrientation: 'from-image' });
}

export async function shrinkImage(file) {
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

// Matches App\Services\PhotoThumbnail::WIDTH. Memory photos ask for a wider copy (data-thumbnail-width).
const THUMBNAIL_WIDTH = 480;

export async function makeThumbnail(file, width = THUMBNAIL_WIDTH) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, width / bitmap.width);
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
