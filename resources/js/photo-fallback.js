// Photos that fail to load: <img data-fallback> shows a calm "Photo not available" tile instead of the
// browser's broken-image icon. Happens when a file was removed from storage, storage is down, or the
// connection drops before a lazy photo (e.g. a later slideshow photo) has loaded.

const PLACEHOLDER = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">'
    + '<rect width="400" height="300" fill="#e2e8f0"/>'
    + '<text x="200" y="140" text-anchor="middle" font-size="44">📷</text>'
    + '<text x="200" y="185" text-anchor="middle" font-family="system-ui,sans-serif" font-size="18" fill="#475569">Photo not available</text>'
    + '</svg>',
);

function showPlaceholder(image) {
    if ('broken' in image.dataset) {
        return; // already swapped; never loop if something goes wrong with the placeholder itself
    }

    image.dataset.broken = '';
    image.alt = 'Photo not available';
    image.removeAttribute('srcset');
    image.src = PLACEHOLDER;
}

// "error" doesn't bubble, so listen in the capture phase to catch it from any image on the page.
document.addEventListener('error', (event) => {
    if (event.target instanceof HTMLImageElement && 'fallback' in event.target.dataset) {
        showPlaceholder(event.target);
    }
}, true);

// Photos that already failed before this script loaded: finished loading (complete) but have no size.
document.querySelectorAll('img[data-fallback]').forEach((image) => {
    if (image.complete && image.naturalWidth === 0 && image.getAttribute('src')) {
        showPlaceholder(image);
    }
});
