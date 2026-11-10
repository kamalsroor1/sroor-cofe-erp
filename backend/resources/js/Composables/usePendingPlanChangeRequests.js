import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';

// Shared by the console nav badge and the plan-change queue page, so an invoice or reject
// updates the badge without a route change.
const pendingCount = ref(0);

/** Re-reads the pending plan-change / add-on requests count (one-row page, meta.total only). */
export async function refreshPendingPlanChangeRequests() {
    const centralAuth = useCentralAuthStore();
    if (!centralAuth.can(CENTRAL_PERMISSIONS.billingView)) return;
    try {
        const res = await api.get('/super-admin/billing/plan-change-requests', {
            params: { status: 'pending', per_page: 1 },
            silent: true,
        });
        pendingCount.value = Number(res.data?.meta?.total) || 0;
    } catch {
        // The badge is a hint; the page itself shows its own error state.
    }
}

/** Clears the badge when the operator session ends. */
export function resetPendingPlanChangeRequests() {
    pendingCount.value = 0;
}

/** Pending plan-change requests count for the console nav badge. */
export function usePendingPlanChangeRequests() {
    const route = useRoute();

    onMounted(refreshPendingPlanChangeRequests);
    watch(() => route.path, refreshPendingPlanChangeRequests);

    return { pendingCount, refresh: refreshPendingPlanChangeRequests };
}
