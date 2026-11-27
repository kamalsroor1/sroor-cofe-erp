import router from '../router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { canAccessMatchedRoute } from '../helpers/routeAccess';

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
        return canAccessMatchedRoute(matched, authStore, appConfigStore);
    };

    return { canAccessPath };
}
