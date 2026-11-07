// Keyboard shortcuts: "/" jumps to the page's search box (<input data-search-shortcut>), Escape leaves it.
// Ignored while typing in another field, so "/" can still be typed in text.
document.addEventListener('keydown', (event) => {
    const search = document.querySelector('[data-search-shortcut]');
    const typing = event.target.closest?.('textarea, select, [contenteditable="true"], '
        + 'input:not([type="checkbox"], [type="radio"], [type="button"], [type="submit"], [type="reset"])');

    if (!search || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    if (event.key === '/' && !typing) {
        event.preventDefault();
        search.focus();
        search.select();
    } else if (event.key === 'Escape' && event.target === search) {
        search.blur();
    }
});
