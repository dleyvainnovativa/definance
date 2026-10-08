/*
 | DF.select — a thin wrapper over choices.js so every <select> in the app is
 | searchable, themed (via design tokens, light/dark), and safe to rebuild.
 |
 | The headache this solves: native selects are hard to search and the account
 | pickers have hundreds of options. choices.js keeps the underlying <select> in
 | sync, so existing code that reads `el.value` keeps working — only *writing*
 | options or the value needs to go through this helper.
 |
 | Usage:
 |   DF.select.mount('#parent_id', { clearable:true, placeholder:'— Ninguna —' });
 |   DF.select.setOptions('#parent_id', accounts.map(a => ({ value:a.id, label:`${a.code} · ${a.name}` })), currentId);
 |   DF.select.setValue('#type', 'asset');
 |   DF.select.destroy('#parent_id');   // e.g. before tearing a modal down
 |
 | Re-mounting an already-mounted node is safe: the previous instance is
 | destroyed first, so no stale choices.js instance leaks.
 */
import Choices from 'choices.js';

// node -> Choices instance
const INSTANCES = new WeakMap();

function node(el) {
    return typeof el === 'string' ? document.querySelector(el) : el;
}

/** Read the plain {value,label,disabled,selected} list from a native <select>. */
function optionsFromSelect(sel) {
    return Array.from(sel.options).map((o) => ({
        value: o.value,
        label: o.textContent,
        disabled: o.disabled,
        selected: o.selected,
    }));
}

export function mount(el, opts = {}) {
    const sel = node(el);
    if (!sel || sel.tagName !== 'SELECT') return null;

    // Never leak a previous instance.
    destroy(sel);

    const instance = new Choices(sel, {
        searchEnabled: opts.search !== false,
        searchResultLimit: opts.searchResultLimit ?? 50,
        searchFields: ['label', 'value'],
        shouldSort: opts.sort === true, // keep DOM order by default (accounts are pre-sorted by code)
        allowHTML: false,
        itemSelectText: '',
        removeItemButton: !!opts.clearable,
        placeholder: true,
        placeholderValue: opts.placeholder ?? null,
        searchPlaceholderValue: opts.searchPlaceholder ?? 'Buscar…',
        noResultsText: 'Sin resultados',
        noChoicesText: 'Sin opciones',
        shouldSortItems: false,
        ...(opts.config || {}),
    });

    INSTANCES.set(sel, instance);
    return instance;
}

/**
 * Replace the option list. Pass [{value,label,disabled?}] (preferred) or omit
 * `options` to re-read the current <select> DOM. `selectedValue` is reselected
 * if present. Mounts first if the node wasn't mounted yet.
 */
export function setOptions(el, options, selectedValue = null) {
    const sel = node(el);
    if (!sel) return null;

    let instance = INSTANCES.get(sel);
    if (!instance) instance = mount(sel);
    if (!instance) return null;

    const selSet = Array.isArray(selectedValue) ? new Set(selectedValue.map(String)) : null;
    const list = (Array.isArray(options) ? options : optionsFromSelect(sel)).map((o) => ({
        value: String(o.value ?? ''),
        label: String(o.label ?? ''),
        disabled: !!o.disabled,
        selected: selSet
            ? selSet.has(String(o.value ?? ''))
            : (selectedValue != null ? String(o.value ?? '') === String(selectedValue) : !!o.selected),
    }));

    instance.clearStore();
    // replaceChoices=true so this fully swaps the list.
    instance.setChoices(list, 'value', 'label', true);
    return instance;
}

/** Programmatically select a value (keeps the underlying <select> in sync). */
export function setValue(el, value) {
    const sel = node(el);
    if (!sel) return;
    const instance = INSTANCES.get(sel);
    if (instance) instance.setChoiceByValue(String(value));
    else sel.value = String(value);
}

/** Current value — works whether or not the node is wrapped (select stays synced). */
export function getValue(el) {
    const sel = node(el);
    return sel ? sel.value : null;
}

/** Selected values for a multi-select, as an array of strings. */
export function getValues(el) {
    const sel = node(el);
    return sel ? Array.from(sel.selectedOptions).map((o) => o.value) : [];
}

/** Enable/disable a (possibly choices.js-wrapped) select. */
export function setDisabled(el, disabled) {
    const sel = node(el);
    if (!sel) return;
    const instance = INSTANCES.get(sel);
    if (instance) { disabled ? instance.disable() : instance.enable(); }
    else { sel.disabled = !!disabled; }
}

/** Tear down the wrapper, restoring the plain <select>. */
export function destroy(el) {
    const sel = node(el);
    if (!sel) return;
    const instance = INSTANCES.get(sel);
    if (instance) {
        instance.destroy();
        INSTANCES.delete(sel);
    }
}

/** Mount every <select> matching a selector (e.g. a page's filter bar). */
export function mountAll(selector, opts = {}) {
    document.querySelectorAll(selector).forEach((sel) => mount(sel, opts));
}

export const select = { mount, mountAll, setOptions, setValue, getValue, getValues, setDisabled, destroy };
