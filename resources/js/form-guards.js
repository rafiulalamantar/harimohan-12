// Checks just before a form is sent. Runs after confirm.js ("Are you sure?") and network-status.js (offline),
// so a form those stopped is left alone.
//
// 1. Upload size: Vercel rejects requests over 4.5 MB with its own error page, and everything typed is lost.
//    Photos are shrunk in the browser first (photos/process.js), but GIFs are not, so check the total here.
// 2. <form data-submit-once>: the Save button is disabled after the first click, so a double tap on slow
//    mobile data doesn't create the same post twice.

const MAX_UPLOAD_BYTES = 4 * 1024 * 1024; // under Vercel's 4.5 MB, leaving room for the text fields

function totalUploadBytes(form) {
    return [...form.querySelectorAll('input[type="file"]')]
        .flatMap((input) => [...(input.files ?? [])])
        .reduce((total, file) => total + file.size, 0);
}

function showFormError(form, message) {
    let error = form.querySelector('[data-form-error]');

    if (!error) {
        error = document.createElement('p');
        error.dataset.formError = '';
        error.setAttribute('role', 'alert');
        error.className = 'rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-200';
        form.querySelector('button:not([type="button"])')?.before(error);
    }

    error.textContent = message;
    error.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

const megabytes = (bytes) => (bytes / 1024 / 1024).toFixed(1);

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelector('[data-form-error]')?.remove();

    const uploadBytes = totalUploadBytes(form);
    if (uploadBytes > MAX_UPLOAD_BYTES) {
        event.preventDefault();
        showFormError(form, `These photos are too big to send together (${megabytes(uploadBytes)} MB; the limit is ${megabytes(MAX_UPLOAD_BYTES)} MB). `
            + 'Remove a photo or choose smaller ones. GIFs are not made smaller automatically. Nothing was sent, and what you typed is still here.');
        return;
    }

    if ('submitOnce' in form.dataset) {
        const button = form.querySelector('button:not([type="button"])');
        if (button) {
            button.disabled = true;
            button.textContent = 'Saving…';
        }
    }
});

// Coming back with the browser's Back button can show the page as it was left (button still "Saving…").
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('form[data-submit-once] button[disabled]').forEach((button) => {
            button.disabled = false;
            button.textContent = 'Save';
        });
    }
});
