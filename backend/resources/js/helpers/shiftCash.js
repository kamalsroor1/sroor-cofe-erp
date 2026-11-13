import { dCmp, dSub } from './decimal.js';

/**
 * Drawer difference: counted cash minus the expected cash. DEC-SHIFT blind count: the close dialogs no longer
 * preview it (the expected cash is not known before the close); the closed shift's Z report carries the server figure.
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
 * DEC-SHIFT: who may close an open shift (the server enforces the same rule, 403 otherwise). The
 * close permission, AND either the user opened the shift or is a manager (reports.view; admin
 * passes every permission check).
 *
 * @param {{ canClose: boolean, isManager: boolean, isOwn: boolean }} rule
 * @returns {boolean}
 */
export function canCloseShift({ canClose, isManager, isOwn }) {
    return Boolean(canClose && (isOwn || isManager));
}

/**
 * True when the shift (as returned by GET /shifts/current: `user_id`) was opened by the user.
 * A shift without an opener is never "own".
 *
 * @param {{ user_id?: number|string|null }|null|undefined} shift
 * @param {number|string|null|undefined} userId
 * @returns {boolean}
 */
export function isShiftOpenedBy(shift, userId) {
    if (!shift || shift.user_id === null || shift.user_id === undefined) return false;
    if (userId === null || userId === undefined) return false;
    return String(shift.user_id) === String(userId);
}

/**
 * True when the counted-cash field holds a usable amount: typed, numeric and not negative, at most 3 decimals (money is DECIMAL(12,3)).
 * The counted field starts empty (blind count: no expected cash is shown before the close), so an empty value is never valid.
 *
 * @param {string|number|null|undefined} value
 * @returns {boolean}
 */
export function isValidCountedCash(value) {
    if (value === null || value === undefined) return false;
    return /^(\d+(\.\d{0,3})?|\.\d{1,3})$/.test(String(value).trim());
}
