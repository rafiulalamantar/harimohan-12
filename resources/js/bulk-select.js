// Bulk actions on lists: a "select all" box (<input data-select-all="students[]">) ticks every row box with
// that name, and the bulk button (<button data-bulk-count>) shows how many are picked and is off at zero.
// Only boxes on screen count: lists have separate phone and desktop markup with the same row boxes.
const visibleBoxes = (name) => [...document.querySelectorAll(`input[type="checkbox"][name="${name}"]`)]
    .filter((box) => box.offsetParent !== null);

function refresh(name) {
    const boxes = visibleBoxes(name);
    const picked = boxes.filter((box) => box.checked).length;

    document.querySelectorAll(`[data-select-all="${name}"]`).forEach((all) => {
        all.checked = boxes.length > 0 && picked === boxes.length;
        all.indeterminate = picked > 0 && picked < boxes.length;
    });

    document.querySelectorAll(`[data-bulk-count="${name}"]`).forEach((button) => {
        button.disabled = picked === 0;
        button.querySelector('[data-bulk-number]').textContent = picked;
    });
}

document.addEventListener('change', (event) => {
    const box = event.target;

    if (box.dataset?.selectAll) {
        visibleBoxes(box.dataset.selectAll).forEach((row) => {
            row.checked = box.checked;
        });
        refresh(box.dataset.selectAll);
    } else if (box.type === 'checkbox' && document.querySelector(`[data-select-all="${box.name}"]`)) {
        refresh(box.name);
    }
});

document.querySelectorAll('[data-select-all]').forEach((all) => refresh(all.dataset.selectAll));
