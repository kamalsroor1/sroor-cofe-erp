import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { notifyWarning } from '../helpers/alert';
import { nextSelection, paymentIdFromQuery } from '../helpers/billingReview';
import { useTrans } from './useTrans';
import { useBillingPendingCount } from './useBillingPendingCount';
import { useCompactViewport } from './useMediaQuery';

/**
 * Payment review workspace (super-admin console), Api\Central\BillingReviewController:
 *   GET /super-admin/billing/payments?status=&page=&per_page=
 * Owns the queue, the selection (mirrored in `?payment=` so e-mail / bell links open the detail) and the refresh
 * after a decision. The decision form lives in useVerifyDecision, the receipt in useBillingReceipt.
 */

const ENDPOINT = '/super-admin/billing/payments';
const PER_PAGE = 20;
const LOCATE_PER_PAGE = 100;

export const PAYMENT_TABS = Object.freeze(['pending', 'verified', 'rejected']);

export function useBillingPayments() {
    const { t } = useTrans();
    const route = useRoute();
    const router = useRouter();
    const centralAuth = useCentralAuthStore();
    const { pendingCount, refreshPendingCount } = useBillingPendingCount();
    const compact = useCompactViewport();

    const status = ref('pending');
    const page = ref(1);
    const payments = ref([]);
    const meta = ref({});
    const isLoading = ref(true);
    const loadError = ref('');
    const selectedId = ref(null);

    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.billingManage));
    const lastPage = computed(() => Number(meta.value?.last_page) || 1);
    const total = computed(() => Number(meta.value?.total) || payments.value.length);
    const selected = computed(() => payments.value.find((item) => item.id === selectedId.value) || null);

    const syncQuery = (id) => {
        const query = { ...route.query };
        if (id) query.payment = String(id);
        else delete query.payment;
        router.replace({ query });
    };

    const select = (id) => {
        selectedId.value = id;
        syncQuery(id);
    };

    const fetchStatus = (state, pageNumber, perPage) =>
        centralApi.get(ENDPOINT, { params: { status: state, page: pageNumber, per_page: perPage } });

    const fetchPayments = async () => {
        isLoading.value = true;
        loadError.value = '';
        try {
            const response = await fetchStatus(status.value, page.value, PER_PAGE);
            payments.value = response.data?.data || [];
            meta.value = response.data?.meta || {};
        } catch (error) {
            payments.value = [];
            loadError.value = error.userMessage || t('billing.console.queue.load_failed');
        } finally {
            isLoading.value = false;
        }
        if (selectedId.value && !selected.value) selectedId.value = null;
        // Desktop shows the detail pane next to the queue: never leave it empty while rows exist.
        if (!selectedId.value && !compact.value && payments.value.length && !paymentIdFromQuery(route.query.payment))
            selectedId.value = payments.value[0].id;
    };

    const setStatus = (next) => {
        if (next === status.value) return;
        status.value = next;
        page.value = 1;
        selectedId.value = null;
        syncQuery(null);
        fetchPayments();
    };

    const goToPage = (next) => {
        if (next < 1 || next > lastPage.value || next === page.value) return;
        page.value = next;
        selectedId.value = null;
        fetchPayments();
    };

    // The API has no single-payment read or id filter: a deep link looks in the first 100 rows of each tab.
    const locate = async (id) => {
        for (const state of PAYMENT_TABS) {
            try {
                const response = await fetchStatus(state, 1, LOCATE_PER_PAGE);
                if ((response.data?.data || []).some((item) => item.id === id)) {
                    status.value = state;
                    page.value = 1;
                    selectedId.value = id;
                    return true;
                }
            } catch {
                return false;
            }
        }
        return false;
    };

    const openDeepLink = async () => {
        const id = paymentIdFromQuery(route.query.payment);
        if (!id) return;
        selectedId.value = id;
        if (payments.value.some((item) => item.id === id)) return;
        if (await locate(id)) {
            await fetchPayments();
            return;
        }
        selectedId.value = null;
        syncQuery(null);
        notifyWarning(t('billing.console.verify.deep_link_not_found'));
    };

    // After a decision: reload and move to the neighbour row (desktop) or back to the queue (compact).
    const afterDecision = async (decidedId) => {
        const neighbour = nextSelection(payments.value, decidedId);
        if (payments.value.length === 1 && page.value > 1) page.value -= 1;
        selectedId.value = !compact.value && neighbour ? neighbour.id : null;
        await Promise.all([fetchPayments(), refreshPendingCount()]);
        syncQuery(selectedId.value);
    };

    // A notification clicked while the page is already open.
    watch(
        () => route.query.payment,
        (value) => {
            const id = paymentIdFromQuery(value);
            if (id && id !== selectedId.value) openDeepLink();
        }
    );

    onMounted(async () => {
        refreshPendingCount();
        await fetchPayments();
        await openDeepLink();
    });

    return {
        status,
        page,
        lastPage,
        total,
        payments,
        isLoading,
        loadError,
        selectedId,
        selected,
        canManage,
        pendingCount,
        fetchPayments,
        setStatus,
        goToPage,
        select,
        afterDecision,
    };
}
