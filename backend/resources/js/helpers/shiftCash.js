import { dCmp, dSub } from './decimal.js';

/**
 * Live drawer difference shown while closing a shift: counted cash minus the expected cash.
 * Both sides may be 3-decimal strings from the API or a half-typed form value; the math is exact
 * (helpers/decimal.js). Returns null while nothing has been counted yet.
 *
 * @param {string|number|null|undefined} actual   counted cash typed by the cashier
 * @param {string|number|null|undefined} expected expected cash in the drawer (server value)
 * @returns {{ amount: string, sign: -1|0|1 } | null}
 */
export function drawerDifference(actual, expected) {
    if (actual === null || actual === undefined || String(actual).trim() === '') return null;
    const amount = dSub(actual, expected ?? '0');
    return { amount, sign: dCmp(amount, '0') };
}
