// ROLE-01: where a signed-in user lands by default. Shared by the login redirect, the forced password
// change exit and the router guard so the rule lives in one place (unit tested in tests/js/landing-path.test.js).

export const DASHBOARD_PATH = '/';
export const POS_PATH = '/pos';

/**
 * A user who can sell but cannot read reports (the cashier) has no use for the dashboard: land on the till.
 * Everyone else keeps the dashboard.
 *
 * @param {{ can: (permission: string) => boolean, hasFeature?: (feature: string) => boolean }} access
 * @returns {string}
 */
export function defaultLandingPath({ can, hasFeature } = {}) {
    if (typeof can !== 'function') return DASHBOARD_PATH;
    const canSell = can('pos.access') && (typeof hasFeature !== 'function' || hasFeature('pos.access'));
    return canSell && !can('reports.view') ? POS_PATH : DASHBOARD_PATH;
}

/** Same rule bound to the two Pinia stores. */
export function landingPathFor(authStore, appConfigStore) {
    return defaultLandingPath({
        can: (permission) => authStore.hasPermission(permission),
        hasFeature: (feature) => appConfigStore.hasFeature(feature),
    });
}
