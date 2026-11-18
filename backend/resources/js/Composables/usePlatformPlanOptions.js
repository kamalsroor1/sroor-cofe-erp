import { ref, onMounted } from 'vue';
import centralApi from '../Services/centralApi';

/**
 * Plans the signup settings can point at (GET /super-admin/plans, super_admin.plans.view).
 * An operator without that permission, or a failed load, leaves `available` false and the plan
 * selectors read-only; the rest of the settings page keeps working.
 */
export function usePlatformPlanOptions() {
    const plans = ref([]);
    const available = ref(true);

    onMounted(async () => {
        try {
            const res = await centralApi.get('/super-admin/plans');
            plans.value = (res.data?.data?.plans || []).map(({ id, name, is_active: isActive, is_free: isFree }) => ({
                id,
                name,
                isActive: !!isActive,
                isFree: !!isFree,
            }));
        } catch {
            available.value = false;
        }
    });

    return { plans, available };
}
