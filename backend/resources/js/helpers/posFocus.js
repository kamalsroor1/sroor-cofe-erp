import { nextTick } from 'vue';

/**
 * DOM focus helpers for the POS cart. The cart renders each line twice (touch card + desktop row),
 * so the visible element wins.
 */
const isVisible = (el) => el.offsetParent !== null;

const focusVisibleField = (lineId, field) => {
    const nodes = document.querySelectorAll(`[data-line-id="${lineId}"][data-line-field="${field}"]`);
    const target = Array.from(nodes).find(isVisible);
    if (!target) return false;
    target.focus();
    target.select?.();
    return true;
};

/**
 * Focus (and select) the qty, price or discount input of a cart line. Returns true when something was focused
 * or is about to be: the desktop row keeps its line discount collapsed, so it is opened first.
 */
export function focusLineField(lineId, field) {
    if (focusVisibleField(lineId, field)) return true;
    if (field !== 'discount') return false;
    const toggle = Array.from(document.querySelectorAll(`[data-line-discount-toggle][data-line-id="${lineId}"]`)).find(
        isVisible
    );
    if (!toggle) return false;
    toggle.click();
    nextTick(() => focusVisibleField(lineId, field));
    return true;
}

/** Id of the cart line that contains the focused element, or null. */
export function focusedLineId() {
    const row = document.activeElement?.closest?.('[data-line-id]');
    return row ? row.dataset.lineId : null;
}

/** Return focus to the POS search / scan box. */
export function focusPosSearch() {
    document.querySelector('[data-pos-search]')?.focus();
}
