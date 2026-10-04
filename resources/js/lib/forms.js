/*
 | Form helpers.
 | - serialize(form) -> plain object. Repeated names (name="legs[]") collapse
 |   into arrays. Unchecked checkboxes are omitted; checked ones become true.
 | - fill(form, data) -> populate fields from an object.
 */
export function serialize(form) {
    const el = typeof form === 'string' ? document.querySelector(form) : form;
    const data = {};
    if (!el) return data;

    for (const field of el.elements) {
        if (!field.name || field.disabled) continue;
        if (field.type === 'checkbox') {
            if (field.checked) data[field.name] = true;
            continue;
        }
        if (field.type === 'radio') {
            if (field.checked) data[field.name] = field.value;
            continue;
        }

        const key = field.name;
        const value = field.value;
        if (key.endsWith('[]')) {
            const k = key.slice(0, -2);
            (data[k] ??= []).push(value);
        } else if (key in data) {
            data[key] = [].concat(data[key], value);
        } else {
            data[key] = value;
        }
    }
    return data;
}

export function fill(form, data = {}) {
    const el = typeof form === 'string' ? document.querySelector(form) : form;
    if (!el) return;
    for (const [name, value] of Object.entries(data)) {
        const field = el.elements[name];
        if (!field) continue;
        if (field.type === 'checkbox') field.checked = Boolean(value);
        else field.value = value ?? '';
    }
}

export const forms = { serialize, fill };
