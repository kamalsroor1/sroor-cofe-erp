import { computed, ref } from 'vue';
import api from '@/Services/api';
import DarkSwal, {
    DANGER_CONFIRM_BUTTON_CLASS,
    DARK_SWAL_CUSTOM_CLASS,
    notifyError,
    notifySuccess,
} from '@/helpers/alert';
import { trans } from '@/helpers/trans';

const HISTORY_PER_PAGE = 20;

/**
 * Tenant plan-change / add-on requests: history list, submit and cancel.
 * Support reviews each request and issues the invoice; nothing switches automatically.
 * `onChanged` re-reads the subscription (pending cards) and invoices after any write.
 */
export function usePlanChangeRequests(subscription, onChanged) {
    const requests = ref([]);
    const isLoading = ref(false);
    const hasError = ref(false);
    const isSubmitting = ref(false);
    const cancellingId = ref(null);
    const errors = ref({});

    const pendingPlanChange = computed(() => subscription.value?.pending_plan_change || null);
    const pendingAddon = computed(() => subscription.value?.pending_addon_request || null);
    const availablePlans = computed(() => (subscription.value?.available_plans || []).filter((p) => !p.is_current));
    const availableAddons = computed(() => subscription.value?.available_addons || []);

    const loadRequests = async () => {
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/billing/plan-change-requests', { params: { per_page: HISTORY_PER_PAGE } });
            requests.value = res.data?.data || [];
        } catch {
            hasError.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const refreshAll = async () => {
        await Promise.all([onChanged(), loadRequests()]);
    };

    /** Returns true when the request was accepted; field errors land in `errors`. */
    const submit = async (payload) => {
        isSubmitting.value = true;
        errors.value = {};
        try {
            const res = await api.post('/billing/plan-change-requests', payload);
            notifySuccess(res.data?.message || trans('subscription.billing.requests.title'));
            await refreshAll();
            return true;
        } catch (e) {
            errors.value = e.response?.data?.errors || {};
            if (!Object.keys(errors.value).length) notifyError(e.userMessage);
            if (e.response?.status === 422) await refreshAll();
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    const cancel = async (request) => {
        const result = await DarkSwal.fire({
            icon: 'warning',
            title: trans('subscription.billing.requests.cancel_title'),
            text: trans('subscription.billing.requests.cancel_text'),
            showCancelButton: true,
            confirmButtonText: trans('subscription.billing.requests.cancel_confirm'),
            cancelButtonText: trans('subscription.billing.requests.cancel_keep'),
            reverseButtons: true,
            customClass: { ...DARK_SWAL_CUSTOM_CLASS, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
        });
        if (!result.isConfirmed) return;
        cancellingId.value = request.id;
        try {
            const res = await api.post(`/billing/plan-change-requests/${request.id}/cancel`);
            notifySuccess(res.data?.message || trans('subscription.billing.requests.cancel'));
        } catch (e) {
            notifyError(e.userMessage);
        } finally {
            cancellingId.value = null;
            await refreshAll();
        }
    };

    return {
        requests,
        isLoading,
        hasError,
        isSubmitting,
        cancellingId,
        errors,
        pendingPlanChange,
        pendingAddon,
        availablePlans,
        availableAddons,
        loadRequests,
        submit,
        cancel,
    };
}
