import router from '../router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';

/**
 * Single source of truth for page access: `meta.permission` (user role) and `meta.feature` (tenant plan)
 * declared on the route table. Navigation (sidebar, launcher tiles, user menu) asks the router instead
 * of repeating permission or feature names.
 */
export function useRoutePermission() {
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();

    const canAccessPath = (path) => {
        const { matched } = router.resolve(path);
        const required = matched.map((record) => record.meta?.permission).find(Boolean);
        if (required && !authStore.hasPermission(required)) return false;
        const requiredAny = matched.map((record) => record.meta?.permissionAny).find(Boolean);
        if (requiredAny && !requiredAny.some((permission) => authStore.hasPermission(permission))) return false;
        const feature = matched.map((record) => record.meta?.feature).find(Boolean);
        return !feature || appConfigStore.hasFeature(feature);
    };

    return { canAccessPath };
}
