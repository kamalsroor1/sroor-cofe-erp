/**
 * POS-07 discount math, exact at scale 3 (mirrors SaleDiscountGuard / InvoiceService on the server).
 *
 * A discount is a percent or a fixed amount, on a cart line (`line.discount_type` / `line.discount_value`)
 * and/or on the whole invoice. Everything here is a preview: the server resolves the real amounts from its
 * own prices, enforces the store's cap and stays the authority for every 422.
 */
import { normalize, dMul, dDiv, dPercent, dMin, dSub, dAdd, dSum, dCmp, isPositive } from './decimal.js';

export const DISCOUNT_PERCENT = 'percentage';
export const DISCOUNT_FIXED = 'fixed';

/** Quantity x price of a cart line, before its discount. */
export function lineGross(line) {
    return dMul(line.quantity, line.unit_price);
}

/** Discount of one line in money: a percent of the line total or a fixed amount, never above the line total. */
export function lineDiscountAmount(line) {
    const value = line.discount_value ?? '0';
    if (!isPositive(value)) return '0.000';
    const gross = lineGross(line);
    const amount = line.discount_type === DISCOUNT_FIXED ? normalize(value) : dPercent(gross, value);
    return dMin(amount, gross);
}

/** Line total after its own discount. */
export function lineNet(line) {
    return dSub(lineGross(line), lineDiscountAmount(line));
}

/** Invoice discount in money, taken off the sum of the line nets and never above it. */
export function invoiceDiscountAmount(subtotal, discountType, discountValue) {
    if (!isPositive(discountValue)) return '0.000';
    const amount = discountType === DISCOUNT_PERCENT ? dPercent(subtotal, discountValue) : normalize(discountValue);
    return dMin(amount, subtotal);
}

/**
 * Totals of the cart's discounts.
 * `exceedsCap` compares total x 100 with cap x gross (the server does the same, exactly, at scale 6;
 * the preview rounds the product at scale 3, so it can only differ from the server on a sub-0.001 tie).
 */
export function summarizeDiscounts({
    cart = [],
    discountType = DISCOUNT_PERCENT,
    discountValue = '0',
    maxPercent = null,
}) {
    const gross = dSum(cart.map(lineGross));
    const lineDiscounts = dSum(cart.map(lineDiscountAmount));
    const subtotal = dSub(gross, lineDiscounts);
    const invoiceDiscount = invoiceDiscountAmount(subtotal, discountType, discountValue);
    const totalDiscount = dAdd(lineDiscounts, invoiceDiscount);

    const hasCap = maxPercent !== null && maxPercent !== undefined && maxPercent !== '';
    const exceedsCap =
        hasCap && isPositive(totalDiscount) && dCmp(dMul(totalDiscount, '100'), dMul(maxPercent, gross)) > 0;
    // For display only (rounded half-up at scale 3).
    const percentOfGross = isPositive(gross) ? dDiv(dMul(totalDiscount, '100'), gross) : '0.000';

    return { gross, lineDiscounts, subtotal, invoiceDiscount, totalDiscount, percentOfGross, exceedsCap };
}
