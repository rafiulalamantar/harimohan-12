import { cropPhoto } from './cropper';
import { makeThumbnail } from './process';

// Admins re-frame a student's existing photo (admin/students/show.blade.php): the original is loaded into the
// cropper and only the new card thumbnail is uploaded. crossOrigin lets the canvas read photos from the storage host.
async function loadImage(url) {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.src = url;
    await image.decode();

    return createImageBitmap(image);
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-reframe-photo]');
    if (!button) {
        return;
    }

    const form = button.form;
    const label = button.dataset.label ??= button.textContent;
    let failed = false;
    button.disabled = true;
    button.textContent = 'Loading photo…';

    try {
        const bitmap = await loadImage(button.dataset.reframePhoto);
        const cropped = await cropPhoto(bitmap);
        bitmap.close();

        const thumbnail = cropped && await makeThumbnail(cropped);
        if (!thumbnail) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(thumbnail);
        form.querySelector('input[name="photo_thumbnail"]').files = transfer.files;
        button.textContent = 'Saving…';
        form.submit();
    } catch {
        failed = true;
    } finally {
        if (!form.querySelector('input[name="photo_thumbnail"]').files.length) {
            button.disabled = false;
            button.textContent = failed ? 'Could not load the photo. Try again' : label;
        }
    }
});
