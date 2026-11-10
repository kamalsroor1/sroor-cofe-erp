import { dCmp } from './decimal.js';

export const REFUND_CASH = 'cash';
export const REFUND_BALANCE = 'balance';
export const CASH_REFUND_CAP_CODE = 'returns.cash_refund_exceeds_paid';

/**
 * Default refund method: walk-in is always cash (the server forces it too); a named customer gets cash
 * only when the invoice still allows a cash refund covering the return total, otherwise balance.
 * `refundable` / `total` are 3-decimal strings; a null `refundable` means unknown (balance).
 */
export function defaultRefundMethod({ walkIn = false, refundable = null, total = '0' } = {}) {
    if (walkIn) return REFUND_CASH;
    if (refundable === null || refundable === undefined) return REFUND_BALANCE;
    return dCmp(total, '0') > 0 && dCmp(refundable, total) >= 0 ? REFUND_CASH : REFUND_BALANCE;
}

/** True when a cash refund of `total` would exceed what the invoice allows (only for known caps). */
export function exceedsCashRefundable({ refundable = null, total = '0' } = {}) {
    if (refundable === null || refundable === undefined) return false;
    return dCmp(total, refundable) > 0;
}

/** True when the customer record is the generic cash customer. */
export function isWalkInCustomer(customer) {
    if (!customer) return false;
    return (customer.is_walk_in ?? customer.phone === '0000000000') === true;
}

/** True when the server rejected a cash refund above what was paid on the invoice. */
export function isCashRefundCapError(error) {
    return error?.response?.status === 422 && error.response.data?.error_code === CASH_REFUND_CAP_CODE;
}

/** Server message of a failed return, or the fallback. */
export function refundErrorMessage(error, fallback) {
    const data = error?.response?.data;
    const first = Object.values(data?.errors || {}).flat()[0];
    return first || data?.message || fallback;
}
