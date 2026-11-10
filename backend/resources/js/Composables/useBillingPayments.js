import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import DarkSwal, {
    DANGER_CONFIRM_BUTTON_CLASS,
    confirmDialog,
    notifyError,
    notifySuccess,
    notifyWarning,
} from '../helpers/alert';
import { useTrans } from './useTrans';
import { useMoney } from './useMoney';
import { useBillingPendingCount } from './useBillingPendingCount';

/**
 * Manual payment review queue (super-admin console), Api\Central\BillingReviewController:
 *   GET  /super-admin/billing/payments?status=&page=&per_page=
 *   GET  /super-admin/billing/payments/{id}/receipt        -> { url, expires_at } (signed, 5 min)
 *   POST /super-admin/billing/payments/{id}/verify | reject {reason}
 * Step-up (fresh 2FA) on writes is handled by centralApi.
 */

const ENDPOINT = '/super-admin/billing/payments';
const PER_PAGE = 20;

export const PAYMENT_TABS = Object.freeze(['pending', 'verified', 'rejected']);
export const REJECT_REASON_MIN = 3;
export const REJECT_REASON_MAX = 500;

export function useBillingPayments() {
    const { t } = useTrans();
    const { formatMoney } = useMoney();
    const centralAuth = useCentralAuthStore();
    const { refreshPendingCount } = useBillingPendingCount();

    const status = ref('pending');
    const page = ref(1);
    const payments = ref([]);
    const meta = ref({});
    const isLoading = ref(true);
    const loadError = ref('');
    const busyId = ref(null);

    const receiptPayment = ref(null);
    const receipt = ref({ state: 'idle', objectUrl: '', kind: '' });

    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.billingManage));
    const lastPage = computed(() => Number(meta.value?.last_page) || 1);

    const fetchPayments = async () => {
        isLoading.value = true;
        loadError.value = '';
        try {
            const response = await centralApi.get(ENDPOINT, {
                params: { status: status.value, page: page.value, per_page: PER_PAGE },
            });
            payments.value = response.data?.data || [];
            meta.value = response.data?.meta || {};
        } catch (error) {
            payments.value = [];
            loadError.value = error.userMessage || t('billing.console.queue.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    const setStatus = (next) => {
        if (next === status.value) return;
        status.value = next;
        page.value = 1;
        fetchPayments();
    };

    const goToPage = (next) => {
        if (next < 1 || next > lastPage.value || next === page.value) return;
        page.value = next;
        fetchPayments();
    };

    const describe = (payment) => ({
        tenant: payment.tenant?.name || '',
        amount: formatMoney(payment.amount, 3),
        currency: payment.currency || '',
        invoice: payment.invoice_number || '',
    });

    const afterReview = async (message) => {
        notifySuccess(message);
        // A page that just lost its last row would be empty: step back one page.
        if (payments.value.length === 1 && page.value > 1) page.value -= 1;
        await Promise.all([fetchPayments(), refreshPendingCount()]);
    };

    const reportFailure = (error) => {
        if (error.stepUpCancelled) {
            notifyWarning(error.userMessage);
            return;
        }
        if (error.response?.status === 403 && !error.response?.data?.error_code) return;
        notifyError(t('billing.console.queue.action_failed'), error.userMessage || '');
    };

    const verifyPayment = async (payment) => {
        if (!canManage.value || busyId.value) return;
        const confirmed = await confirmDialog({
            title: t('billing.console.queue.verify_title'),
            text: t('billing.console.queue.verify_text', describe(payment)),
            confirmButtonText: t('billing.console.queue.verify_confirm'),
        });
        if (!confirmed) return;
        busyId.value = payment.id;
        try {
            const response = await centralApi.post(`${ENDPOINT}/${payment.id}/verify`);
            await afterReview(response.data?.message || '');
        } catch (error) {
            reportFailure(error);
        } finally {
            busyId.value = null;
        }
    };

    const askRejectReason = async (payment) => {
        const result = await DarkSwal.fire({
            title: t('billing.console.queue.reject_title'),
            text: t('billing.console.queue.reject_text', describe(payment)),
            icon: 'warning',
            input: 'textarea',
            inputLabel: t('billing.console.queue.reason_label'),
            inputPlaceholder: t('billing.console.queue.reason_placeholder'),
            inputAttributes: { maxlength: String(REJECT_REASON_MAX) },
            showCancelButton: true,
            confirmButtonText: t('billing.console.queue.reject_confirm'),
            cancelButtonText: t('common.cancel'),
            reverseButtons: true,
            customClass: { ...DarkSwal.params.customClass, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
            inputValidator: (value) => {
                const length = String(value || '').trim().length;
                if (length < REJECT_REASON_MIN) return t('billing.console.queue.reason_required');
                if (length > REJECT_REASON_MAX) return t('billing.console.queue.reason_too_long');
                return undefined;
            },
        });
        return result.isConfirmed ? String(result.value).trim() : null;
    };

    const rejectPayment = async (payment) => {
        if (!canManage.value || busyId.value) return;
        const reason = await askRejectReason(payment);
        if (!reason) return;
        busyId.value = payment.id;
        try {
            const response = await centralApi.post(`${ENDPOINT}/${payment.id}/reject`, { reason });
            await afterReview(response.data?.message || '');
        } catch (error) {
            reportFailure(error);
        } finally {
            busyId.value = null;
        }
    };

    const releaseReceipt = () => {
        if (receipt.value.objectUrl) URL.revokeObjectURL(receipt.value.objectUrl);
    };

    const closeReceipt = () => {
        releaseReceipt();
        receiptPayment.value = null;
        receipt.value = { state: 'idle', objectUrl: '', kind: '' };
    };

    // The signed link is single-use: minted and consumed once here, never exposed to the UI.
    // Retry re-mints a fresh link; "open in new tab" uses the blob URL. The link is fetched on open; the file is read once into a blob so the viewer can tell an
    // image from a PDF by its real content type and keep working after the 5-minute link expires.
    const openReceipt = async (payment) => {
        closeReceipt();
        receiptPayment.value = payment;
        receipt.value = { state: 'loading', objectUrl: '', kind: '' };
        const target = payment.id;
        try {
            const link = await centralApi.get(`${ENDPOINT}/${payment.id}/receipt`);
            const url = link.data?.data?.url;
            const file = await fetch(url);
            if (!file.ok) throw new Error('receipt');
            const blob = await file.blob();
            const kind = blob.type === 'application/pdf' ? 'pdf' : blob.type.startsWith('image/') ? 'image' : '';
            if (receiptPayment.value?.id !== target) return;
            if (!kind) throw new Error('receipt');
            receipt.value = { state: 'ready', objectUrl: URL.createObjectURL(blob), kind };
        } catch (error) {
            if (receiptPayment.value?.id !== target) return;
            receipt.value = { state: 'error', objectUrl: '', kind: '' };
            if (error?.userMessage) notifyError(t('billing.console.receipt.load_failed'), error.userMessage);
        }
    };

    const retryReceipt = () => {
        if (receiptPayment.value) openReceipt(receiptPayment.value);
    };

    onMounted(() => {
        fetchPayments();
        refreshPendingCount();
    });

    onBeforeUnmount(releaseReceipt);

    return {
        status,
        page,
        lastPage,
        payments,
        isLoading,
        loadError,
        busyId,
        canManage,
        receiptPayment,
        receipt,
        fetchPayments,
        setStatus,
        goToPage,
        verifyPayment,
        rejectPayment,
        openReceipt,
        retryReceipt,
        closeReceipt,
    };
}
