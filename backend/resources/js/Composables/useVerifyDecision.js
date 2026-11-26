import { ref, computed, watch, onBeforeUnmount } from 'vue';
import centralApi from '../Services/centralApi';
import { notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { fromDatetimeLocal } from '../helpers/planEditor';
import { canApprove, emptyChecks, isFutureLocal, isPositiveMoney } from '../helpers/billingReview';
import { useTrans } from './useTrans';

const ENDPOINT = '/super-admin/billing/payments';
const PREVIEW_DEBOUNCE_MS = 400;
export const REJECT_REASON_MIN = 3;
export const REJECT_REASON_MAX = 500;

const errorCodeOf = (error) => error.response?.data?.error_code || null;

/**
 * The decision form of one pending payment (#232 UI 4.1): the actual transfer time, the three confirmations,
 * the server's verify-preview (refetched, debounced, when the time changes) and approve / reject.
 * `onDecided(paymentId)` runs after a decision (or after finding it was already taken).
 */
export function useVerifyDecision(payment, onDecided) {
    const { t } = useTrans();

    const transferredAt = ref('');
    const checks = ref(emptyChecks());
    const fieldError = ref('');
    const busy = ref(false);
    const preview = ref({ state: 'idle', data: null });
    const rejectOpen = ref(false);
    const rejectReason = ref('');

    let timer = null;
    let ticket = 0;

    const isFuture = computed(() => isFutureLocal(transferredAt.value));
    const timeError = computed(() =>
        isFuture.value ? t('billing.console.verify.transfer_time_future') : fieldError.value
    );
    const approvable = computed(
        () =>
            !busy.value &&
            canApprove({ transferredAt: transferredAt.value, checks: checks.value, future: isFuture.value })
    );
    const reasonLength = computed(() => rejectReason.value.trim().length);
    const reasonValid = computed(
        () => reasonLength.value >= REJECT_REASON_MIN && reasonLength.value <= REJECT_REASON_MAX
    );
    const hasOverpaid = computed(() => isPositiveMoney(preview.value.data?.overpaid_amount));

    const reset = () => {
        window.clearTimeout(timer);
        ticket += 1;
        transferredAt.value = '';
        checks.value = emptyChecks();
        fieldError.value = '';
        busy.value = false;
        preview.value = { state: 'idle', data: null };
        rejectOpen.value = false;
        rejectReason.value = '';
    };

    const fetchPreview = async () => {
        const iso = fromDatetimeLocal(transferredAt.value);
        const target = payment.value;
        if (!iso || !target || isFuture.value) {
            preview.value = { state: 'idle', data: null };
            return;
        }
        const mine = ++ticket;
        // Keep the old numbers (dimmed by the UI) while the new ones load.
        preview.value = { state: 'loading', data: preview.value.data };
        try {
            const response = await centralApi.get(`${ENDPOINT}/${target.id}/verify-preview`, {
                params: { transferred_at: iso },
                silent: true,
            });
            if (mine !== ticket) return;
            preview.value = { state: 'ready', data: response.data?.data || null };
        } catch (error) {
            if (mine !== ticket) return;
            const message = error.response?.status === 422 ? error.response.data?.errors?.transferred_at?.[0] : '';
            if (message) fieldError.value = message;
            preview.value = { state: 'error', data: null };
        }
    };

    watch(transferredAt, () => {
        fieldError.value = '';
        window.clearTimeout(timer);
        ticket += 1;
        if (!transferredAt.value || isFuture.value) {
            preview.value = { state: 'idle', data: null };
            return;
        }
        preview.value = { state: 'loading', data: preview.value.data };
        timer = window.setTimeout(fetchPreview, PREVIEW_DEBOUNCE_MS);
    });

    watch(() => payment.value?.id, reset);
    onBeforeUnmount(() => window.clearTimeout(timer));

    const reportFailure = async (error) => {
        if (error.stepUpCancelled) {
            notifyWarning(error.userMessage);
            return;
        }
        if (error.response?.status === 409 && errorCodeOf(error) === 'billing.payment_not_pending') {
            notifyWarning(t('billing.console.verify.already_decided'), error.userMessage || '');
            await onDecided(payment.value.id);
            return;
        }
        if (error.response?.status === 422 && error.response.data?.errors?.transferred_at) {
            fieldError.value = error.response.data.errors.transferred_at[0];
            return;
        }
        if (error.response?.status === 403 && !error.response?.data?.error_code) return;
        notifyError(t('billing.console.queue.action_failed'), error.userMessage || '');
    };

    const approve = async () => {
        if (!approvable.value) return;
        const target = payment.value;
        busy.value = true;
        try {
            const response = await centralApi.post(`${ENDPOINT}/${target.id}/verify`, {
                transferred_at: fromDatetimeLocal(transferredAt.value),
            });
            const verified = response.data?.data || {};
            notifySuccess(response.data?.message || t('billing.console.verify.approve'));
            if (isPositiveMoney(verified.invoice_overpaid_amount)) {
                notifyWarning(
                    t('billing.console.verify.verified_overpaid', { amount: verified.invoice_overpaid_amount }),
                    t('billing.console.verify.preview_overpaid_note')
                );
            }
            if (verified.term_already_ended) notifyWarning(t('billing.console.verify.term_already_ended'));
            await onDecided(target.id);
        } catch (error) {
            await reportFailure(error);
        } finally {
            busy.value = false;
        }
    };

    const openReject = () => {
        rejectReason.value = '';
        rejectOpen.value = true;
    };

    const reject = async () => {
        if (!reasonValid.value || busy.value) return;
        const target = payment.value;
        busy.value = true;
        try {
            const response = await centralApi.post(`${ENDPOINT}/${target.id}/reject`, {
                reason: rejectReason.value.trim(),
            });
            notifySuccess(response.data?.message || t('billing.console.verify.reject'));
            rejectOpen.value = false;
            await onDecided(target.id);
        } catch (error) {
            await reportFailure(error);
        } finally {
            busy.value = false;
        }
    };

    return {
        transferredAt,
        checks,
        timeError,
        busy,
        approvable,
        preview,
        hasOverpaid,
        rejectOpen,
        rejectReason,
        reasonLength,
        reasonValid,
        approve,
        openReject,
        reject,
    };
}
