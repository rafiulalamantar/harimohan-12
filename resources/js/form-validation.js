// Inline validation: once someone leaves a field, show what is wrong right under it (wrong email, 10-digit
// phone, empty required field) instead of waiting for the server. Uses the rules already on the inputs
// (required, type, pattern, min/max), so the server stays the real check.
const SKIPPED_TYPES = ['hidden', 'file', 'submit', 'button', 'checkbox', 'radio'];

function messageFor(field) {
    if (field.validity.patternMismatch && field.title) {
        return field.title;
    }

    return field.validationMessage;
}

function showFieldError(field) {
    let error = field.parentElement.querySelector('[data-live-error]');

    if (field.validity.valid) {
        error?.remove();
        field.removeAttribute('aria-invalid');
        return;
    }

    if (!error) {
        error = document.createElement('p');
        error.dataset.liveError = '';
        error.id = `${field.id || field.name}-live-error`;
        error.className = 'text-sm text-rose-600 mt-1.5';
        field.after(error);
        field.setAttribute('aria-describedby', error.id);
    }

    error.textContent = messageFor(field);
    field.setAttribute('aria-invalid', 'true');
}

const checkable = (field) => field instanceof HTMLElement && field.form && field.willValidate
    && !SKIPPED_TYPES.includes(field.type);

document.addEventListener('focusout', (event) => {
    const field = event.target;

    // Leaving an empty field untouched isn't a mistake yet; the browser still checks it on submit.
    if (checkable(field) && (field.value !== '' || field.dataset.touched)) {
        field.dataset.touched = '';
        showFieldError(field);
    }
});

document.addEventListener('input', (event) => {
    const field = event.target;

    if (checkable(field) && 'touched' in field.dataset) {
        showFieldError(field);
    }
});

// The browser blocks a submit with invalid fields; show every message, not just its first bubble.
document.addEventListener('invalid', (event) => {
    const field = event.target;

    if (checkable(field)) {
        field.dataset.touched = '';
        showFieldError(field);
    }
}, true);
