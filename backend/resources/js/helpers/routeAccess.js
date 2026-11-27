/**
 * Pure route-level permission, role, and feature checks shared by the Vue Router guard
 * (`router/index.js`) and navigation/UI visibility (`useRoutePermission.js`).
 */

export function hasRoutePermission(meta, authStore) {
    if (!meta || !authStore) return true;
    if (meta.permission && !authStore.hasPermission(meta.permission)) return false;
    if (meta.permissionAny && !meta.permissionAny.some((permission) => authStore.hasPermission(permission))) {
        return false;
    }
    if (meta.role && !authStore.hasRole(meta.role)) return false;
    return true;
}

export function canAccessMatchedRoute(matched, authStore, appConfigStore) {
    const records = Array.isArray(matched) ? matched : [];
    const required = records.map((record) => record.meta?.permission).find(Boolean);
    if (required && !authStore.hasPermission(required)) return false;
    const requiredAny = records.map((record) => record.meta?.permissionAny).find(Boolean);
    if (requiredAny && !requiredAny.some((permission) => authStore.hasPermission(permission))) return false;
    const requiredRole = records.map((record) => record.meta?.role).find(Boolean);
    if (requiredRole && !authStore.hasRole(requiredRole)) return false;
    const feature = records.map((record) => record.meta?.feature).find(Boolean);
    return !feature || appConfigStore.hasFeature(feature);
}

export function deniedRouteRedirect(meta, authStore) {
    if (hasRoutePermission(meta, authStore)) return null;
    return {
        messageKey: 'common.no_access',
        redirect: { name: 'dashboard' },
    };
}
