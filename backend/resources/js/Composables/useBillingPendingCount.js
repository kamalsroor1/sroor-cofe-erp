import { ref } from 'vue';
import centralApi from '../Services/centralApi';

/**
 * Number of manual payments waiting for review (nav badge). Module-level so the layout badge and the
 * review page share one value; a failed read keeps the last known count.
 */
const pendingCount = ref(null);

export const resetBillingPendingCount = () => {
    pendingCount.value = null;
};

export function useBillingPendingCount() {
    const refreshPendingCount = async () => {
        try {
            const response = await centralApi.get('/super-admin/billing/payments', {
                params: { status: 'pending', per_page: 1 },
                silent: true,
            });
            const total = Number(response.data?.meta?.total);
            pendingCount.value = Number.isFinite(total) ? total : null;
        } catch {
            // the badge is a hint only; the review page shows the real error
        }
    };

    return { pendingCount, refreshPendingCount };
}
