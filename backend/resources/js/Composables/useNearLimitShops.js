import { ref, computed, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';

/**
 * PLAN-14: shops at >= 80% of any plan limit (upsell list), GET /super-admin/near-limit-shops.
 * Super-admin only (super_admin.plans.manage). The server scans shop by shop and caches the result
 * for 10 minutes, so `refresh()` asks for a fresh scan (slower).
 */
export const NEAR_LIMIT_THRESHOLD = 80;

export function useNearLimitShops() {
    const centralAuth = useCentralAuthStore();

    const rows = ref([]);
    const meta = ref(null);
    const page = ref(1);
    const isLoading = ref(false);
    const hasError = ref(false);

    const canView = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.plansManage));

    let latest = 0;
    const fetchRows = async ({ refresh = false } = {}) => {
        if (!canView.value) return;
        const ticket = ++latest;
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/super-admin/near-limit-shops', {
                params: { page: page.value, threshold: NEAR_LIMIT_THRESHOLD, refresh: refresh ? 1 : undefined },
            });
            if (ticket !== latest) return;
            rows.value = res.data?.data || [];
            meta.value = res.data?.meta || null;
        } catch {
            if (ticket === latest) hasError.value = true;
        } finally {
            if (ticket === latest) isLoading.value = false;
        }
    };

    const goToPage = (next) => {
        page.value = next;
        fetchRows();
    };

    const refresh = () => {
        page.value = 1;
        return fetchRows({ refresh: true });
    };

    onMounted(fetchRows);

    return { rows, meta, isLoading, hasError, canView, fetchRows, goToPage, refresh };
}
