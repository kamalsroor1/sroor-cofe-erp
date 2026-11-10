import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';

// Shared by the console nav badge and the signup-requests page, so an approve/reject
// updates the badge without a route change.
const pendingCount = ref(0);

/** Re-reads the pending signup requests count (one-row page, meta.total only). */
export async function refreshPendingSignupRequests() {
    const centralAuth = useCentralAuthStore();
    if (!centralAuth.can(CENTRAL_PERMISSIONS.tenantsView)) return;
    try {
        const res = await api.get('/super-admin/signup-requests', {
            params: { status: 'pending', per_page: 1 },
            silent: true,
        });
        pendingCount.value = Number(res.data?.meta?.total) || 0;
    } catch {
        // The badge is a hint; the page itself shows its own error state.
    }
}

/** Clears the badge when the operator session ends. */
export function resetPendingSignupRequests() {
    pendingCount.value = 0;
}

/** Pending signup requests count for the console nav badge. */
export function usePendingSignupRequests() {
    const route = useRoute();

    onMounted(refreshPendingSignupRequests);
    watch(() => route.path, refreshPendingSignupRequests);

    return { pendingCount, refresh: refreshPendingSignupRequests };
}
