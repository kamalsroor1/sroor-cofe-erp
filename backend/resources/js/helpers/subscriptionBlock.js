// SELL-07 / code review R1: the "suspended for non-payment, /billing only" session flag
// (localStorage auth_payment_required). The login payload sets it (`subscription_blocked`); /auth/me
// carries the derived lifecycle state (`subscription.access`). Once that access is no longer
// `blocked` (paid, or a 48h temporary access opened by a full-amount receipt) the flag must clear,
// or the SPA keeps the shop on /billing although the API lets it in. Pure helper
// (tests/js/subscription-block.test.js).

/**
 * The flag after an /auth/me payload: false when the payload says the shop is not blocked any
 * more, else unchanged (an unknown shape never unblocks nor blocks a session).
 */
export function paymentRequiredAfterMe(payload, current) {
    const access = payload?.subscription?.access;
    if (typeof access !== 'string' || access === '') return current === true;
    return access === 'blocked' ? current === true : false;
}
