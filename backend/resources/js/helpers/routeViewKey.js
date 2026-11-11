/**
 * Routes whose view must NOT remount when the active branch changes. The POS holds an open cart and reloads
 * its own catalog/shift data when the store switches, so remounting it would silently drop the cashier's cart.
 */
export const STORE_AGNOSTIC_ROUTE_NAMES = Object.freeze(['pos.index']);

/**
 * The `:key` of the tenant shell's <router-view> component. Every page remounts (and so refetches with the new
 * `X-Store-Id`) when the branch changes, except the store-agnostic routes above, which key on the route only.
 */
export function routeViewKey(route, storeId) {
    if (STORE_AGNOSTIC_ROUTE_NAMES.includes(route?.name)) return route.fullPath;
    return `${route?.fullPath}-${storeId}`;
}
