import { formatMoneyAmount } from './formatters';
import { trans } from './trans';

export { yearlySavingPercent } from './landingCatalog.js';

/** A catalog price string for display: whole amounts lose the trailing zeros ("499.000" -> "499"). */
export function formatCatalogPrice(value) {
    return formatMoneyAmount(value, { decimals: 2 });
}

/** A limit / count for display (Western digits, no decimals). */
export function formatCatalogCount(value) {
    return formatMoneyAmount(value, { decimals: 0 });
}

/** The catalog currency code as a word (landing.currencies.<code>); unknown codes are shown as they are. */
export function catalogCurrencyLabel(currency) {
    const key = `landing.currencies.${String(currency).toLowerCase()}`;
    const label = trans(key);
    return label === key ? currency : label;
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
