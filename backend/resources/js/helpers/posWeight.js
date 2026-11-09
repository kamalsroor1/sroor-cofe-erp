import { dDiv, dMul, isPositive, dRoundTo } from './decimal';
import { toWesternDigits } from './digits';

/** Quick weights of the picker, in the item's weight unit (kg). */
export const WEIGHT_PRESETS = [
    { key: 'quarter', qty: '0.250', labelKey: 'pos.weight_quick_quarter' },
    { key: 'half', qty: '0.500', labelKey: 'pos.weight_quick_half' },
    { key: 'three_quarters', qty: '0.750', labelKey: 'pos.weight_quick_three_quarters' },
    { key: 'kilo', qty: '1.000', labelKey: 'pos.weight_quick_kilo' },
];

/** Keeps Western digits and a single decimal point; accepts Arabic-Indic digits and the Arabic decimal mark. */
export function sanitizeDecimalInput(raw) {
    return toWesternDigits(raw)
        .replace(/[^0-9.]/g, '')
        .replace(/^(\d*\.\d*)\./, '$1');
}

/** Quantity bought with `amount` money at `unitPrice` per unit (scale 3, half-up). '0.000' without a price. */
export function qtyFromAmount(amount, unitPrice) {
    if (!isPositive(unitPrice)) return '0.000';
    return dDiv(amount, unitPrice);
}

/** Line total of a quantity at a unit price (scale 3, half-up). */
export function lineTotal(qty, unitPrice) {
    return dMul(qty, unitPrice);
}

/** Quantity text as typed, trimmed to 3 decimals (rounded half-up only when more were typed). */
export function limitQuantityText(text) {
    const clean = sanitizeDecimalInput(text);
    return /^\d*\.?\d{0,3}$/.test(clean) ? clean : dRoundTo(clean, 3);
}
