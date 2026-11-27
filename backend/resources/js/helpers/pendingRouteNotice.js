/**
 * Holds a route-guard notice (e.g. `common.no_access`) until both:
 *  1) the Vue Router redirect has settled (`router.afterEach`), AND
 *  2) the initial SystemBootSplash (`z-[999999]`) has finished hiding (`markAppReady`).
 *
 * Without this, a cold URL load to a forbidden route (e.g. cashier typing `/users`)
 * fires `notifyError` inside `beforeEach` while the boot splash still covers the screen
 * and before translations / theme state have finished mounting.
 */

let appReady = false;
let pendingMessageKey = null;

export function queueRouteNotice(messageKey) {
    if (typeof messageKey === 'string' && messageKey.length > 0) {
        pendingMessageKey = messageKey;
    }
}

export function isAppReady() {
    return appReady;
}

export function hasPendingRouteNotice() {
    return pendingMessageKey !== null;
}

export function flushPendingRouteNotice(notify) {
    if (!pendingMessageKey || typeof notify !== 'function') {
        return false;
    }
    const key = pendingMessageKey;
    pendingMessageKey = null;
    notify(key);
    return true;
}

export function markAppReady(notify) {
    appReady = true;
    return flushPendingRouteNotice(notify);
}

export function resetPendingRouteNotice() {
    appReady = false;
    pendingMessageKey = null;
}
