import router from '../router';
import { useAuthStore } from '../stores/auth';

/**
 * Single source of truth for page access: the `meta.permission` declared on the route table.
 * Navigation (sidebar, launcher tiles, user menu) asks the router instead of repeating permission names.
 */
export function useRoutePermission() {
    const authStore = useAuthStore();

    const canAccessPath = (path) => {
        const { matched } = router.resolve(path);
        const required = matched.map((record) => record.meta?.permission).find(Boolean);
        return !required || authStore.hasPermission(required);
    };

    return { canAccessPath };
}
