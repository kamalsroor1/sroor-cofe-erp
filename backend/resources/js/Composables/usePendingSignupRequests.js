import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';

/** Pending signup requests count for the console nav badge (one-row page, meta.total only). */
export function usePendingSignupRequests() {
    const route = useRoute();
    const centralAuth = useCentralAuthStore();
    const pendingCount = ref(0);

    const refresh = async () => {
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
    };

    onMounted(refresh);
    watch(() => route.path, refresh);

    return { pendingCount, refresh };
}
