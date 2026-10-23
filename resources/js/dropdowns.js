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
