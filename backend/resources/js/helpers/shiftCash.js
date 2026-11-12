import { dCmp, dSub } from './decimal.js';

/**
 * Live drawer difference shown while closing a shift: counted cash minus the expected cash.
 * Both sides may be 3-decimal strings from the API or a half-typed form value; the math is exact
 * (helpers/decimal.js). Returns null while nothing has been counted yet or the expected cash is unknown.
 *
 * @param {string|number|null|undefined} actual   counted cash typed by the cashier
 * @param {string|number|null|undefined} expected expected cash in the drawer (server value)
 * @returns {{ amount: string, sign: -1|0|1 } | null}
 */
export function drawerDifference(actual, expected) {
    if (actual === null || actual === undefined || String(actual).trim() === '') return null;
    // expected is null when the server withholds it (no reports.view): no difference can be previewed.
    if (expected === null || expected === undefined || String(expected).trim() === '') return null;
    const amount = dSub(actual, expected);
    return { amount, sign: dCmp(amount, '0') };
}

/**
 * True when the counted-cash field holds a usable amount: typed, numeric and not negative, at most 3 decimals (money is DECIMAL(12,3)).
 * The counted field starts empty (blind count), so an empty value is never valid.
 *
 * @param {string|number|null|undefined} value
 * @returns {boolean}
 */
export function isValidCountedCash(value) {
    if (value === null || value === undefined) return false;
    return /^(\d+(\.\d{0,3})?|\.\d{1,3})$/.test(String(value).trim());
}
