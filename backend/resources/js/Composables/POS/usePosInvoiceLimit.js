import { computed, onMounted, ref } from 'vue';
import api from '../../Services/api';
import { useAuthStore } from '../../stores/auth';
import { trans } from '../../helpers/trans';
import { showUpgradeAlert } from '../../helpers/planLimitAlert';
import { isPosInvoiceLimitNear, isPosInvoiceLimitReached } from '../../helpers/planLimit';
import { useFormatters } from '../useFormatters';

/**
 * PLAN-13: where the shop stands against its monthly sales-invoice cap, for the cashier screen.
 * Only the checkout is ever blocked (by the server: 403 limit_reached); the POS just says so early.
 * Reads, returns, debt collection and shift close are never touched. The status is a hint: the server
 * stays the authority, so a failed read leaves `status` null and the sale is simply attempted.
 */
export function usePosInvoiceLimit() {
    const auth = useAuthStore();
    const { formatDate } = useFormatters();
    const status = ref(null);

    const reached = computed(() => isPosInvoiceLimitReached(status.value));
    const nearLimit = computed(() => isPosInvoiceLimitNear(status.value));
    const canUpgrade = computed(() => auth.hasPermission('billing.manage'));

    const refresh = async () => {
        try {
            const res = await api.get('/pos/invoice-limit');
            status.value = res.data?.data ?? null;
        } catch {
            // A hint only: keep the last known status.
        }
    };

    /** The message of a refused sale: the cap and when it starts again. */
    const blockedMessage = () =>
        trans('subscription.pos_limit.blocked_text', {
            max: status.value.limit,
            date: status.value.resets_at ? formatDate(status.value.resets_at) : '',
        });

    /**
     * Before a sale: false (with the upgrade alert shown) while the cap is reached. A cached "reached" is
     * re-read first, so a new month or a raised plan never leaves the till locked.
     */
    const allowCheckout = async () => {
        if (!reached.value) return true;
        await refresh();
        if (!reached.value) return true;
        await showUpgradeAlert({ titleKey: 'subscription.alerts.limit_reached_title', message: blockedMessage() });
        return false;
    };

    onMounted(refresh);

    return { status, reached, nearLimit, canUpgrade, refresh, allowCheckout };
}
