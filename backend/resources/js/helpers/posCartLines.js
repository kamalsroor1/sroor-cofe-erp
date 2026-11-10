import { dAdd, dSub, dCmp } from './decimal.js';
import { limitQuantityText } from './posWeight.js';
import { DISCOUNT_FIXED, DISCOUNT_PERCENT } from './posDiscount.js';
import { sanitizeAmountInput } from './posCheckout.js';

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

/** Set the quantity from a typed value; ignores empty, non-numeric and non-positive input. Weighted lines keep decimals (3 max), others are whole. */
export function updateLineQty(cart, { index, value }) {
    if (!hasLine(cart, index)) return false;
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed <= 0) return false;
    if (cart[index].is_weighted) {
        const text = limitQuantityText(String(value));
        if (!(parseFloat(text) > 0)) return false;
        cart[index].quantity = text;
        return true;
    }
    const whole = Math.round(parsed);
    if (whole < 1) return false;
    cart[index].quantity = whole;
    return true;
}

/** Set the unit price from a typed value; ignores empty, non-numeric and negative input. */
export function updateLinePrice(cart, { index, value }) {
    if (!hasLine(cart, index)) return false;
    const parsed = parseFloat(value);
    if (isNaN(parsed) || parsed < 0) return false;
    cart[index].unit_price = parsed;
    cart[index].price_overridden = true;
    return true;
}

/**
 * POS-07: set a line's discount from what the cashier typed. `type` ('percentage' | 'fixed') and `value` are
 * both optional: a missing one keeps the current setting. A percent is capped at 100; an amount larger than the
 * line total is clamped when the totals are computed, so the typed text is kept as is.
 */
export function updateLineDiscount(cart, { index, type, value }) {
    if (!hasLine(cart, index)) return false;
    const line = cart[index];
    if (type === DISCOUNT_PERCENT || type === DISCOUNT_FIXED) line.discount_type = type;
    if (value !== undefined) {
        let text = sanitizeAmountInput(value);
        const [whole, fraction] = text.split('.');
        if (fraction !== undefined) text = `${whole || '0'}.${fraction.slice(0, 3)}`;
        if ((line.discount_type || DISCOUNT_PERCENT) === DISCOUNT_PERCENT && parseFloat(text) > 100) text = '100';
        line.discount_value = text;
    }
    return true;
}

export function removeLine(cart, index) {
    if (!hasLine(cart, index)) return false;
    cart.splice(index, 1);
    return true;
}

export function increaseLineQty(cart, index) {
    if (!hasLine(cart, index)) return false;
    if (cart[index].is_weighted) {
        cart[index].quantity = dAdd(cart[index].quantity, String(WEIGHT_STEP));
        return true;
    }
    cart[index].quantity = parseFloat(cart[index].quantity) + 1;
    return true;
}

/** Decrease by one (weighted lines by 0.250); a line that would reach zero is removed. */
export function decreaseLineQty(cart, index) {
    if (!hasLine(cart, index)) return false;
    if (cart[index].is_weighted) {
        const next = dSub(cart[index].quantity, String(WEIGHT_STEP));
        if (dCmp(next, '0.001') < 0) return removeLine(cart, index);
        cart[index].quantity = next;
        return true;
    }
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

const positive = (v) => {
    const n = parseFloat(v);
    return n > 0 ? n : 0;
};

/** Tier price of an item or cart line: wholesale falls back to retail; min_selling_price is a floor, never a price. */
export function priceFor(item, tier) {
    if (!item) return 0;
    const retail = positive(item.selling_price) || positive(item.price_retail) || positive(item.price);
    const wholesale = positive(item.wholesale_price) || positive(item.price_wholesale);
    return tier === 'wholesale' ? wholesale || retail : retail;
}

/** Re-price cart lines for a tier; lines the cashier priced by hand and lines without a tier price are kept. */
export function repriceLines(cart, tier) {
    cart.forEach((line) => {
        if (line.price_overridden) return;
        const price = priceFor(line, tier);
        if (price > 0) line.unit_price = price;
    });
}
