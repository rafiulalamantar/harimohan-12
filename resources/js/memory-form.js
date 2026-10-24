// Admin memory form (admin/memories/form.blade.php): the photo limit follows the chosen type.
// Each type's radio button carries its limit in data-photo-limit (6, or 1 for Remembering).
// When the type changes, the file picker allows one or several photos and the hint below it says so.
// The server checks the same limit again (MemoryRequest), so this is only to help the admin.

const photoInput = document.querySelector('[data-photo-limit-input]');
const hint = document.querySelector('[data-photo-limit-hint]');

function applyPhotoLimit(limit) {
    photoInput.multiple = limit > 1;

    if (hint) {
        hint.textContent = limit > 1
            ? `Up to ${limit} photos per memory. Pick several at once; big photos are shrunk before upload.`
            : 'One photo of your friend. Big photos are shrunk before upload.';
    }

    // Several photos were already picked, but this type allows fewer: clear the choice so they pick again.
    if (photoInput.files.length > limit) {
        photoInput.value = '';
    }
}

if (photoInput) {
    document.querySelectorAll('input[name="kind"][data-photo-limit]').forEach((radio) => {
        radio.addEventListener('change', () => applyPhotoLimit(Number(radio.dataset.photoLimit)));
    });
}
