import { cropPhoto } from './cropper';
import { decodeImage, makeThumbnail, shrinkImage } from './process';

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
