/**
 * POS cart line edits. The cart array is owned by the POS view (one per open order);
 * child components only emit the requested change and the view applies it here,
 * so no component ever mutates a prop.
 *
 * Every function mutates the given cart array in place and returns true when a line changed.
 */

/** Step used by the weight-aware stepper in the touch cart card. */
export const WEIGHT_STEP = 0.25;
/** Below this weight the touch stepper removes the line instead of decreasing it. */
export const WEIGHT_MIN = 0.125;

const hasLine = (cart, index) => Array.isArray(cart) && index >= 0 && index < cart.length;

/** Set the quantity from a typed value; ignores empty, non-numeric and non-positive input. */
export function updateLineQty(cart, { index, value }) {
    if (!hasLine(cart, index)) return false;
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed <= 0) return false;
    cart[index].quantity = parsed;
    return true;
}

/** Set the unit price from a typed value; ignores empty, non-numeric and negative input. */
export function updateLinePrice(cart, { index, value }) {
    if (!hasLine(cart, index)) return false;
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return false;
    cart[index].unit_price = parsed;
    return true;
}

export function removeLine(cart, index) {
    if (!hasLine(cart, index)) return false;
    cart.splice(index, 1);
    return true;
}

export function increaseLineQty(cart, index) {
    if (!hasLine(cart, index)) return false;
    cart[index].quantity = parseFloat(cart[index].quantity) + 1;
    return true;
}

/** Decrease by one; a line at quantity 1 or less is removed. */
export function decreaseLineQty(cart, index) {
    if (!hasLine(cart, index)) return false;
    if (parseFloat(cart[index].quantity) > 1) {
        cart[index].quantity = parseFloat(cart[index].quantity) - 1;
        return true;
    }
    return removeLine(cart, index);
}

/**
 * Next quantity for the touch stepper (weight lines step by 0.25, others by 1).
 * Returns null when decreasing at or below the minimum, meaning "remove the line".
 */
export function stepQuantity(quantity, { weightBased = false, direction = 1 } = {}) {
    const current = Number(quantity) || 0;
    const step = weightBased ? WEIGHT_STEP : 1;
    if (direction < 0) {
        const min = weightBased ? WEIGHT_MIN : 1;
        if (current <= min) return null;
        return Number((current - step).toFixed(3));
    }
    return Number((current + step).toFixed(3));
}
