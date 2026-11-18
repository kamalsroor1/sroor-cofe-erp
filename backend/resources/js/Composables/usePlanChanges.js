import { ref, onMounted } from 'vue';
import api from '../Services/centralApi';

/**
 * PLAN-14: last console changes of one plan, GET /super-admin/plans/{id}/changes
 * (super_admin.audit.view). Loaded once when the history tab opens.
 */
export function usePlanChanges(planId) {
    const changes = ref([]);
    const isLoading = ref(true);
    const hasError = ref(false);

    const fetchChanges = async () => {
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get(`/super-admin/plans/${planId}/changes`);
            changes.value = res.data?.data || [];
        } catch {
            hasError.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(fetchChanges);

    return { changes, isLoading, hasError, fetchChanges };
}
