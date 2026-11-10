import { formatMoneyAmount } from './formatters';
import { toMilli } from './decimal';

/** A catalog price string for display: whole amounts lose the trailing zeros ("499.000" -> "499"). */
export function formatCatalogPrice(value) {
    return formatMoneyAmount(value, { decimals: 2 });
}

/** A limit / count for display (Western digits, no decimals). */
export function formatCatalogCount(value) {
    return formatMoneyAmount(value, { decimals: 0 });
}

/**
 * Whole-percent saving of paying yearly instead of 12 monthly payments, in BigInt thousandths
 * (display only, never sent anywhere). Returns 0 when there is no saving or a price is missing.
 */
export function yearlySavingPercent(monthly, yearly) {
    const twelveMonths = toMilli(monthly) * 12n;
    const year = toMilli(yearly);
    if (twelveMonths <= 0n || year <= 0n || year >= twelveMonths) return 0;
    return Number(((twelveMonths - year) * 100n) / twelveMonths);
}

export const LIMIT_KEYS = Object.freeze([
    'users',
    'stores',
    'warehouses',
    'vans',
    'items',
    'invoices_per_month',
    'storage_mb',
]);
