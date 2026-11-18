import { computed, onMounted, ref } from 'vue';
import api from '@/Services/api';
import { useAuthStore } from '@/stores/auth';
import { trans } from '@/helpers/trans';
import { limitedRows, worstUsageLevel } from '@/helpers/planLimit';

/**
 * PLAN-13: plan usage for the dashboard widget. Read from the billing summary, so it is loaded only for
 * users who may manage billing (they are also the ones who can upgrade); everyone else sees nothing.
 * `rows` holds the LIMITED resources only (an unlimited one has no row, the server omits it).
 */
export function usePlanUsage() {
    const auth = useAuthStore();
    const usage = ref([]);
    const isLoading = ref(false);
    const error = ref('');

    const isAllowed = computed(() => auth.hasPermission('billing.manage'));
    const rows = computed(() => limitedRows(usage.value));
    const level = computed(() => worstUsageLevel(rows.value));

    const load = async () => {
        if (!isAllowed.value) return;
        isLoading.value = true;
        error.value = '';
        try {
            const res = await api.get('/billing/subscription');
            usage.value = res.data?.data?.usage || [];
        } catch (e) {
            error.value = e.userMessage || trans('subscription.usage_widget.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(load);

    return { rows, level, isAllowed, isLoading, error, load };
}
