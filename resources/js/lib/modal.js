/*
 | Modal helpers — thin wrapper over Bootstrap's Modal.
 | Usage:  modal.open('#editAccount');  modal.close('#editAccount');
 */
import { Modal } from 'bootstrap';

function instance(target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return null;
    return Modal.getOrCreateInstance(el);
}

export const modal = {
    open: (target) => instance(target)?.show(),
    close: (target) => instance(target)?.hide(),
    toggle: (target) => instance(target)?.toggle(),
    instance,
};
