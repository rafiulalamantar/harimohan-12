// Autosaved drafts: <form data-draft="announcement-new"> keeps what is typed in this browser (localStorage),
// so a closed tab, a dead battery or a server error doesn't lose a long announcement or email.
// Saving waits until typing pauses (debounced). The draft is deleted once the next page shows a success
// message; after a validation error or a failed request it stays, and is offered again next time.
const SAVE_DELAY_MS = 600;
const SENT_KEY = 'draft-sent';
const storageKey = (name) => `draft:${name}`;

function storage(area) {
    try {
        return window[area];
    } catch {
        return null; // private mode or blocked site data: drafts are simply off
    }
}

function draftFields(form) {
    return [...form.elements].filter((field) => field.name && !field.disabled
        && !['password', 'file', 'hidden', 'submit', 'button', 'checkbox', 'radio'].includes(field.type));
}

function saveDraft(form) {
    const values = Object.fromEntries(draftFields(form).map((field) => [field.name, field.value]));
    const changed = draftFields(form).some((field) => field.value !== field.defaultValue);

    try {
        if (changed) {
            storage('localStorage')?.setItem(storageKey(form.dataset.draft), JSON.stringify(values));
        } else {
            storage('localStorage')?.removeItem(storageKey(form.dataset.draft));
        }
    } catch {
        // storage full or blocked: keep working without drafts
    }
}

function showRestoredNotice(form) {
    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    notice.className = 'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-sky-50 px-4 py-3 text-sm font-medium text-sky-900 ring-1 ring-sky-200';
    notice.textContent = '📝 We restored what you typed last time. Nothing was lost.';

    const discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'underline';
    discard.textContent = 'Discard draft';
    discard.addEventListener('click', () => {
        draftFields(form).forEach((field) => {
            field.value = field.defaultValue;
        });
        storage('localStorage')?.removeItem(storageKey(form.dataset.draft));
        notice.remove();
    });

    notice.append(discard);
    form.prepend(notice);
}

function restoreDraft(form) {
    let values;
    try {
        values = JSON.parse(storage('localStorage')?.getItem(storageKey(form.dataset.draft)) ?? 'null');
    } catch {
        values = null;
    }

    if (!values) {
        return;
    }

    let restored = false;
    draftFields(form).forEach((field) => {
        if (field.name in values && field.value !== values[field.name]) {
            field.value = values[field.name];
            restored = true;
        }
    });

    if (restored) {
        showRestoredNotice(form);
    }
}

// The page after a submit: a success message means the draft was saved for real.
const sentDraft = storage('sessionStorage')?.getItem(SENT_KEY);
if (sentDraft) {
    storage('sessionStorage')?.removeItem(SENT_KEY);
    if (document.querySelector('[data-flash="success"]')) {
        storage('localStorage')?.removeItem(storageKey(sentDraft));
    }
}

const saveTimers = new WeakMap();

document.querySelectorAll('form[data-draft]').forEach((form) => {
    restoreDraft(form);

    form.addEventListener('input', () => {
        clearTimeout(saveTimers.get(form));
        saveTimers.set(form, setTimeout(() => saveDraft(form), SAVE_DELAY_MS));
    });
});

// On document and imported after confirm.js / form-guards.js, so a cancelled "Are you sure?" or a too-big
// upload has already set defaultPrevented here and isn't counted as sent.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!event.defaultPrevented && form.dataset?.draft) {
        clearTimeout(saveTimers.get(form));
        saveDraft(form);
        storage('sessionStorage')?.setItem(SENT_KEY, form.dataset.draft);
    }
});
