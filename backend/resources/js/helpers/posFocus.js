/**
 * DOM focus helpers for the POS cart. The cart renders each line twice (touch card + desktop row),
 * so the visible element wins.
 */
const isVisible = (el) => el.offsetParent !== null;

/** Focus (and select) the qty or price input of a cart line. Returns true when something was focused. */
export function focusLineField(lineId, field) {
    const nodes = document.querySelectorAll(`[data-line-id="${lineId}"][data-line-field="${field}"]`);
    const target = Array.from(nodes).find(isVisible);
    if (!target) return false;
    target.focus();
    target.select?.();
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
