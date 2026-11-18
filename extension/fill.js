// Fills an application form with what CareerCraft sent: only empty fields, and never the
// submit button. Runs in the page's tab when the person presses "Fill this form".
// It answers with what it filled, so the popup can say what to check by hand.

// Field matchers: tried against a field's name, id, autocomplete hint and label text.
const FIELDS = [
    ['first_name', /first.?name|given.?name|vorname/i],
    ['last_name', /last.?name|family.?name|surname|nachname/i],
    ['full_name', /^name$|full.?name|^urls?\[name\]$|your name/i],
    ['email', /e.?mail/i],
    ['phone', /phone|telefon|mobile/i],
    ['city', /city|location|stadt|wohnort/i],
    ['linkedin', /linkedin/i],
    ['github', /github/i],
    ['website', /website|portfolio|homepage|urls\[other\]/i],
    ['salary_expectation', /salary|gehalt/i],
    ['notice_period', /notice|kündigung/i],
    ['earliest_start', /start.?date|earliest.?start|eintritt|available.?from/i],
    ['work_permit', /work.?permit|authori[sz]ed to work|visa|arbeitserlaubnis/i],
    ['why_company', /why.*(work|join|interested|apply)|warum.*(bewerben|arbeiten|interesse)/i],
    ['why_fit', /why.*(fit|good|hire|qualified)|warum.*(passen|geeignet)/i],
];

// Never filled: questions about gender, ethnicity, disability, veteran status or similar,
// which are the person's own choice to answer.
const PROTECTED = /gender|hispanic|latin|ethnic|race|disab|veteran|sexual|pronoun|religio|geschlecht|behinderung/i;

/** Everything that describes a field: its name, id, autocomplete hint and label. */
function describe(field) {
    const labels = [...(field.labels ?? [])].map((label) => label.textContent).join(' ');
    const labelledBy = (field.getAttribute('aria-labelledby') ?? '').split(/\s+/).map((id) => document.getElementById(id)?.textContent ?? '').join(' ');
    return [field.name, field.id, field.getAttribute('autocomplete'), field.getAttribute('aria-label'), field.placeholder, labels, labelledBy].filter(Boolean).join(' ');
}

/** Sets a value the way typing would, so forms built with React notice it too. */
function setValue(field, value) {
    const prototype = field instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value').set.call(field, value);
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Puts a PDF into a file field, as if it had been chosen. */
function setFile(field, file) {
    const bytes = Uint8Array.from(atob(file.base64), (char) => char.charCodeAt(0));
    const transfer = new DataTransfer();
    transfer.items.add(new File([bytes], file.name, { type: 'application/pdf' }));
    field.files = transfer.files;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * Fills the form. `data` holds `values` (by key, as in FIELDS) and `files` (`cv` and
 * `letter`, each a name and base64 bytes, or missing).
 */
globalThis.careerCraftFill = function (data) {
    const filled = [];
    const used = new Set();

    for (const field of document.querySelectorAll('input:not([type]), input[type=text], input[type=email], input[type=tel], input[type=url], input[type=number], input[type=date], textarea')) {
        // A combobox is a searchable list, not a text field: what's typed must be one of its options.
        if (field.disabled || field.readOnly || field.value !== '' || field.offsetParent === null || field.getAttribute('role') === 'combobox') {
            continue;
        }
        const text = describe(field);
        if (PROTECTED.test(text)) {
            continue;
        }
        // The first matcher wins: "first name" before "name". Each value goes in one field only.
        const match = FIELDS.find(([key, pattern]) => pattern.test(text) && data.values[key] && !used.has(key) && !(key === 'full_name' && /first|last/i.test(text)));
        if (!match) {
            continue;
        }
        const [key] = match;
        const value = String(data.values[key]);
        if (field.type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            continue;
        }
        setValue(field, value);
        filled.push(key);
        used.add(key);
    }

    const fileFields = [...document.querySelectorAll('input[type=file]')].map((field) => {
        const text = describe(field);
        const key = /cover|letter|anschreiben|motivation/i.test(text) ? 'letter' : /resume|cv|lebenslauf/i.test(text) ? 'cv' : null;
        return { field, text, key };
    });
    for (const { field, key } of fileFields) {
        if (field.files.length === 0 && key && data.files[key] && !used.has(key)) {
            setFile(field, data.files[key]);
            filled.push(key);
            used.add(key);
        }
    }
    // Forms without a letter slot, like Personio's, take further documents under "Other".
    const other = fileFields.find(({ field, text, key }) => key === null && field.files.length === 0 && /other|andere|additional|weitere|sonstige/i.test(text));
    if (other && data.files.letter && !fileFields.some(({ key }) => key === 'letter')) {
        setFile(other.field, data.files.letter);
        filled.push('letter');
    }

    return { filled };
};
