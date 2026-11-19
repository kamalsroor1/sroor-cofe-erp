import { ref, computed, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import DarkSwal, { DARK_SWAL_CUSTOM_CLASS, notifySuccess, notifyWarning } from '../helpers/alert';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';

export const ACCOUNT_DELETION_STATUSES = Object.freeze(['pending', 'handled', 'all']);

/** MOB-05: console queue of public account-deletion requests (tabs, search, paging, mark handled). */
export function useAccountDeletionRequests() {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const requests = ref([]);
    const pagination = ref(null);
    const isLoading = ref(false);
    const hasError = ref(false);
    const status = ref('pending');
    const search = ref('');
    const page = ref(1);

    // Cosmetic only: the API enforces super_admin.tenants.manage.
    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.tenantsManage));

    let latest = 0;
    const fetchRequests = async () => {
        const ticket = ++latest;
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/super-admin/account-deletion-requests', {
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

    const markHandled = async (request) => {
        const result = await DarkSwal.fire({
            title: t('account_deletion.console.handle_title'),
            text: `${request.contact_name} - ${request.phone}`,
            input: 'textarea',
            inputLabel: t('account_deletion.console.handle_note_label'),
            inputAttributes: { maxlength: '500' },
            showCancelButton: true,
            confirmButtonText: t('account_deletion.console.handle_confirm'),
            cancelButtonText: t('common.cancel'),
            customClass: DARK_SWAL_CUSTOM_CLASS,
        });
        if (!result.isConfirmed) return;

        try {
            const note = String(result.value || '').trim();
            const res = await api.post(`/super-admin/account-deletion-requests/${request.id}/handle`, {
                note: note || null,
            });
            notifySuccess(res.data?.message || t('account_deletion.console.handled_success'));
        } catch (e) {
            if (e.response?.status === 409) {
                notifyWarning(t('account_deletion.console.already_handled'), e.userMessage);
            } else if (e.response?.status !== 403) {
                notifyWarning(t('account_deletion.console.action_failed'), e.userMessage);
            }
        }
        fetchRequests();
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
        markHandled,
    };
}
