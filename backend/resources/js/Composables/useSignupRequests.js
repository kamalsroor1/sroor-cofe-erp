import { ref, computed, reactive, onMounted } from 'vue';
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
import { useSignupRequestApproval } from './useSignupRequestApproval';
import { refreshPendingSignupRequests } from './usePendingSignupRequests';

export const SIGNUP_STATUSES = Object.freeze(['pending', 'approved', 'rejected']);

/** Console list of public signup requests: tabs, search, paging, approve and reject. */
export function useSignupRequests() {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const requests = ref([]);
    const pagination = ref(null);
    const isLoading = ref(false);
    const hasError = ref(false);
    const status = ref('pending');
    const search = ref('');
    const page = ref(1);

    // Cosmetic only: the API enforces super_admin.tenants.manage (+ recent 2FA for approve).
    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.tenantsManage));

    let latest = 0;
    const fetchRequests = async () => {
        const ticket = ++latest;
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/super-admin/signup-requests', {
                params: { status: status.value, search: search.value || undefined, page: page.value },
            });
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
    const setSearch = (value) => {
        search.value = value;
        page.value = 1;
        fetchRequests();
    };
    const goToPage = (value) => {
        page.value = value;
        fetchRequests();
    };

    const approval = useSignupRequestApproval(() => {
        fetchRequests();
        refreshPendingSignupRequests();
    });

    const reject = async (signup) => {
        const result = await DarkSwal.fire({
            title: t('signup.console.reject_title'),
            text: signup.business_name,
            input: 'textarea',
            inputLabel: t('signup.console.reject_label'),
            inputAttributes: { maxlength: '500' },
            inputValidator: (value) => {
                const length = String(value || '').trim().length;
                return length >= 3 && length <= 500 ? null : t('signup.console.reject_reason_length');
            },
            showCancelButton: true,
            confirmButtonText: t('signup.console.reject_confirm'),
            cancelButtonText: t('common.cancel'),
            customClass: { ...DARK_SWAL_CUSTOM_CLASS, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
        });
        if (!result.isConfirmed) return;

        try {
            const res = await api.post(`/super-admin/signup-requests/${signup.id}/reject`, {
                reason: String(result.value).trim(),
            });
            notifySuccess(res.data?.message || t('signup.statuses.rejected'));
        } catch (e) {
            if (e.response?.status === 409) {
                notifyWarning(t('signup.console.already_handled'), e.userMessage);
            } else if (e.response?.status !== 403) {
                notifyWarning(t('signup.console.action_failed'), e.userMessage);
            }
        }
        fetchRequests();
        refreshPendingSignupRequests();
    };

    onMounted(fetchRequests);

    return {
        requests,
        pagination,
        isLoading,
        hasError,
        status,
        search,
        canManage,
        fetchRequests,
        setStatus,
        setSearch,
        goToPage,
        reject,
        // reactive(): the view reads approval.form / approval.request without .value
        approval: reactive(approval),
    };
}
