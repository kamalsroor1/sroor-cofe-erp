import { ref, computed, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import DarkSwal, {
    DANGER_CONFIRM_BUTTON_CLASS,
    DARK_SWAL_CUSTOM_CLASS,
    notifySuccess,
    notifyWarning,
} from '../helpers/alert';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { refreshPendingPlanChangeRequests } from './usePendingPlanChangeRequests';

export const PLAN_CHANGE_TABS = Object.freeze(['pending', 'invoiced', 'rejected', 'cancelled']);

const BASE = '/super-admin/billing/plan-change-requests';

/** Console queue of tenant plan-change / add-on requests: tabs, paging, issue invoice and reject. */
export function usePlanChangeQueue() {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const requests = ref([]);
    const pagination = ref(null);
    const isLoading = ref(false);
    const hasError = ref(false);
    const status = ref('pending');
    const page = ref(1);
    const plans = ref([]);
    const plansFailed = ref(false);

    const target = ref(null);
    const isSubmitting = ref(false);
    const errors = ref({});
    const issued = ref(null);

    // Cosmetic only: the API enforces super_admin.billing.manage (+ recent 2FA).
    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.billingManage));

    let latest = 0;
    const fetchRequests = async () => {
        const ticket = ++latest;
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get(BASE, { params: { status: status.value, page: page.value } });
            if (ticket !== latest) return;
            requests.value = res.data?.data || [];
            pagination.value = { ...(res.data?.meta || {}), links: res.data?.meta?.links || res.data?.links || [] };
        } catch {
            if (ticket === latest) hasError.value = true;
        } finally {
            if (ticket === latest) isLoading.value = false;
        }
    };

    const setStatus = (value) => {
        status.value = value;
        page.value = 1;
        fetchRequests();
    };
    const goToPage = (value) => {
        page.value = value;
        fetchRequests();
    };

    const afterAction = () => {
        fetchRequests();
        refreshPendingPlanChangeRequests();
    };

    const loadPlans = async () => {
        if (plans.value.length) return;
        plansFailed.value = false;
        try {
            const res = await api.get('/super-admin/plans', { silent: true });
            plans.value = res.data?.data?.plans || [];
        } catch {
            plansFailed.value = true;
        }
    };

    const openIssue = (request) => {
        target.value = request;
        errors.value = {};
        issued.value = null;
        if (request.kind.value === 'plan_change') loadPlans();
    };
    const closeIssue = () => {
        target.value = null;
        issued.value = null;
    };

    const issue = async (body) => {
        isSubmitting.value = true;
        errors.value = {};
        try {
            const res = await api.post(`${BASE}/${target.value.id}/invoice`, body);
            notifySuccess(res.data?.message || t('billing.console.plan_changes.actions.issue'));
            issued.value = res.data?.data?.invoice || null;
            afterAction();
        } catch (e) {
            if (e.response?.status === 422 && e.response.data?.errors) {
                errors.value = e.response.data.errors;
            } else if (e.response?.status === 409) {
                notifyWarning(t('billing.console.plan_changes.reject.already_handled'), e.userMessage);
                closeIssue();
                afterAction();
            } else if (e.response?.status !== 403) {
                notifyWarning(t('billing.console.plan_changes.reject.action_failed'), e.userMessage);
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    const reject = async (request) => {
        const result = await DarkSwal.fire({
            title: t('billing.console.plan_changes.reject.title'),
            text: request.tenant?.name || '',
            input: 'textarea',
            inputLabel: t('billing.console.plan_changes.reject.label'),
            inputAttributes: { maxlength: '500' },
            inputValidator: (value) => {
                const length = String(value || '').trim().length;
                return length >= 3 && length <= 500 ? null : t('billing.console.plan_changes.reject.reason_length');
            },
            showCancelButton: true,
            confirmButtonText: t('billing.console.plan_changes.reject.confirm'),
            cancelButtonText: t('common.cancel'),
            customClass: { ...DARK_SWAL_CUSTOM_CLASS, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
        });
        if (!result.isConfirmed) return;

        try {
            const res = await api.post(`${BASE}/${request.id}/reject`, { reason: String(result.value).trim() });
            notifySuccess(res.data?.message || t('billing.console.plan_changes.actions.reject'));
        } catch (e) {
            if (e.response?.status === 409) {
                notifyWarning(t('billing.console.plan_changes.reject.already_handled'), e.userMessage);
            } else if (e.response?.status !== 403) {
                notifyWarning(t('billing.console.plan_changes.reject.action_failed'), e.userMessage);
            }
        }
        afterAction();
    };

    onMounted(fetchRequests);

    return {
        requests,
        pagination,
        isLoading,
        hasError,
        status,
        canManage,
        plans,
        plansFailed,
        target,
        issued,
        errors,
        isSubmitting,
        fetchRequests,
        setStatus,
        goToPage,
        openIssue,
        closeIssue,
        issue,
        reject,
    };
}
