import { ref, computed, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import DarkSwal, {
    DANGER_CONFIRM_BUTTON_CLASS,
    notifyError,
    notifySuccess,
    notifyWarning,
    Toast,
} from '../helpers/alert';
import { useTenantStatusForm } from './useTenantStatusForm';
import { useCentralAuthStore } from '../stores/centralAuth';
import { TENANT_STATUSES, tenantStatusLabel } from '../helpers/tenantStatus';

const TENANTS_MANAGE_PERMISSION = 'super_admin.tenants.manage';

/** Statuses the server accepts for an archive request (OPS-9: suspend or cancel first). */
export const ARCHIVABLE_STATUSES = Object.freeze(['suspended', 'cancelled']);
const ARCHIVE_ACTIVE_STATES = Object.freeze(['queued', 'running']);

// 409 error codes of DELETE /super-admin/tenants/{id} and POST .../unarchive -> dialog title.
// The body is always the server's translated message.
const ARCHIVE_CONFLICT_TITLES = {
    'subscription.archive_not_allowed': 'tenant_archive.errors.not_allowed_title',
    'subscription.archive_in_progress': 'tenant_archive.errors.in_progress_title',
    'subscription.already_archived': 'tenant_archive.errors.already_archived_title',
    'subscription.tenant_purged': 'tenant_archive.errors.purged_title',
    'subscription.final_backup_required': 'tenant_archive.errors.final_backup_title',
    'subscription.archive_failed': 'tenant_archive.errors.archive_failed_title',
    'subscription.not_archived': 'tenant_archive.errors.not_archived_title',
    'provisioning.workspace_not_ready': 'tenant_archive.errors.not_provisioned_title',
};

export const isArchiveInProgress = (tenant) => ARCHIVE_ACTIVE_STATES.includes(tenant?.archive?.state);

/**
 * OPS-9 archive / unarchive of a tenant from the platform console (list and tenant page).
 * Archiving is asynchronous (202): a job takes and verifies a final backup, then archives.
 * Step-up (fresh 2FA) is handled by centralApi: it prompts and retries the request once.
 */
export function useTenantArchiveActions() {
    const { t } = useTrans();
    const isArchiving = ref(false);
    const isUnarchiving = ref(false);
    const centralAuth = useCentralAuthStore();
    // Cosmetic only: the server enforces super_admin.tenants.manage + a fresh 2FA step-up.
    const canManageTenants = computed(() => centralAuth.can(TENANTS_MANAGE_PERMISSION));

    const showRequestError = (e, fallbackKey) => {
        if (e?.stepUpCancelled) {
            notifyWarning(t('tenant_archive.step_up_cancelled'), e.userMessage);
            return;
        }
        const status = e?.response?.status;
        const code = e?.response?.data?.error_code;
        const message = e?.userMessage || t(fallbackKey);
        if (status === 409) {
            DarkSwal.fire({
                icon: code === 'subscription.archive_in_progress' ? 'info' : 'warning',
                title: t(ARCHIVE_CONFLICT_TITLES[code] || 'tenant_archive.errors.conflict_title'),
                text: message,
                confirmButtonText: t('common.ok'),
            });
            return;
        }
        notifyError(t('common.error'), message);
    };

    /**
     * Typed-slug confirmation, then DELETE (202 = queued).
     * @returns {Promise<object|null>} the server's { id, status, archive } or null when cancelled / refused
     */
    const archiveTenant = async (tenant) => {
        if (!tenant?.id || isArchiving.value) return null;
        const slug = String(tenant.slug || '');

        if (isArchiveInProgress(tenant)) {
            Toast.fire({ icon: 'info', title: t('tenant_archive.already_in_progress') });
            return null;
        }
        if (!ARCHIVABLE_STATUSES.includes(tenant.status)) {
            DarkSwal.fire({
                icon: 'warning',
                title: t('tenant_archive.errors.not_allowed_title'),
                text: t('tenant_archive.suspend_first'),
                confirmButtonText: t('common.ok'),
            });
            return null;
        }

        const result = await DarkSwal.fire({
            icon: 'warning',
            title: t('tenant_archive.confirm_title'),
            text: t('tenant_archive.confirm_desc', { name: tenant.name || slug }),
            input: 'text',
            inputLabel: t('tenant_archive.type_slug_label', { slug }),
            inputPlaceholder: slug,
            inputAttributes: { autocapitalize: 'off', autocomplete: 'off', spellcheck: 'false', dir: 'ltr' },
            inputValidator: (value) =>
                String(value || '').trim() === slug ? null : t('tenant_archive.slug_mismatch', { slug }),
            showCancelButton: true,
            confirmButtonText: t('tenant_archive.confirm_btn'),
            cancelButtonText: t('common.cancel'),
            customClass: { ...DarkSwal.params.customClass, confirmButton: DANGER_CONFIRM_BUTTON_CLASS },
        });
        if (!result.isConfirmed) return null;

        isArchiving.value = true;
        try {
            const res = await api.delete(`/super-admin/tenants/${tenant.id}`, {
                data: { confirm_code: String(result.value).trim(), expected_status: tenant.status },
            });
            notifySuccess(t('tenant_archive.queued_title'), t('tenant_archive.queued_desc'));
            return res.data?.data || null;
        } catch (e) {
            showRequestError(e, 'tenant_archive.archive_failed');
            return null;
        } finally {
            isArchiving.value = false;
        }
    };

    /** @returns {Promise<object|null>} the updated tenant summary, or null */
    const unarchiveTenant = async (tenant) => {
        if (!tenant?.id || isUnarchiving.value) return null;
        const result = await DarkSwal.fire({
            icon: 'question',
            title: t('tenant_archive.unarchive_confirm_title'),
            text: t('tenant_archive.unarchive_confirm_desc', { name: tenant.name || tenant.slug || '' }),
            showCancelButton: true,
            confirmButtonText: t('tenant_archive.unarchive_btn'),
            cancelButtonText: t('common.cancel'),
        });
        if (!result.isConfirmed) return null;

        isUnarchiving.value = true;
        try {
            const res = await api.post(`/super-admin/tenants/${tenant.id}/unarchive`);
            notifySuccess(t('common.success'), res.data?.message || t('tenant_archive.unarchived'));
            return res.data?.data || { id: tenant.id };
        } catch (e) {
            showRequestError(e, 'tenant_archive.unarchive_failed');
            return null;
        } finally {
            isUnarchiving.value = false;
        }
    };

    return { isArchiving, isUnarchiving, canManageTenants, archiveTenant, unarchiveTenant };
}

export function useSuperAdminTenants() {
    const { t } = useTrans();

    const tenants = ref([]);
    const plansList = ref([]);
    const isLoading = ref(false);
    const isSubmitting = ref(false);

    const filters = ref({
        search: '',
        status: 'all',
        plan_id: 'all',
    });

    const statusOptions = computed(() => [
        { value: 'all', label: t('super.all_statuses') },
        ...TENANT_STATUSES.map((status) => ({ value: status, label: tenantStatusLabel(status, t) })),
    ]);

    const planOptions = computed(() => [
        { value: 'all', label: t('super.all_plans') },
        ...plansList.value.map((p) => ({ value: p.id, label: p.name })),
    ]);

    const showCreateModal = ref(false);
    const showStatusModal = ref(false);
    const selectedTenant = ref(null);

    const createForm = ref({
        name: '',
        slug: '',
        email: '',
        phone: '',
        password: '',
        plan_id: null,
        trial_days: 14,
        tenancy_db_name: '',
        tenancy_db_username: '',
        tenancy_db_password: '',
    });

    const { statusForm, statusErrors, isSubmittingStatus, resetStatusForm, updateStatusField, submitStatus } =
        useTenantStatusForm();
    const { isArchiving, isUnarchiving, canManageTenants, archiveTenant, unarchiveTenant } = useTenantArchiveActions();

    let debounceTimer = null;

    const fetchTenants = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/super-admin/tenants', { params: filters.value });
            tenants.value = res.data?.tenants?.data || res.data?.tenants || [];
            plansList.value = res.data?.plans || [];
            if (!createForm.value.plan_id && plansList.value.length > 0) {
                createForm.value.plan_id = plansList.value[0].id;
            }
        } catch (e) {
            console.error('Failed to load tenants:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const updateSearch = (val) => {
        filters.value.search = val;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchTenants();
        }, 300);
    };

    const updateStatusFilter = (val) => {
        filters.value.status = val;
        fetchTenants();
    };

    const updatePlanFilter = (val) => {
        filters.value.plan_id = val;
        fetchTenants();
    };

    const openCreateModal = () => {
        createForm.value = {
            name: '',
            slug: '',
            email: '',
            phone: '',
            password: '',
            plan_id: plansList.value[0]?.id || null,
            trial_days: 14,
            tenancy_db_name: '',
            tenancy_db_username: '',
            tenancy_db_password: '',
        };
        showCreateModal.value = true;
    };

    const updateCreateField = (field, val) => {
        createForm.value[field] = val;
    };

    const submitCreateTenant = async () => {
        isSubmitting.value = true;
        try {
            await api.post('/super-admin/tenants', createForm.value);
            DarkSwal.fire({
                icon: 'success',
                title: t('super.tenant_created_title'),
                text: t('super.tenant_created_msg'),
                timer: 2000,
                showConfirmButton: false,
            });
            showCreateModal.value = false;
            fetchTenants();
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.tenant_create_failed'),
                confirmButtonText: t('common.ok'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    const openStatusModal = (tenant) => {
        selectedTenant.value = tenant;
        resetStatusForm(tenant?.status || null);
        showStatusModal.value = true;
    };

    const submitStatusChange = async () => {
        const updated = await submitStatus(selectedTenant.value?.id);
        if (!updated) return;
        showStatusModal.value = false;
        fetchTenants();
    };

    // OPS-9: "delete" archives (async, reversible until purge). The name is kept for the existing table event.
    const confirmDeleteTenant = async (tenant) => {
        if (await archiveTenant(tenant)) fetchTenants();
    };

    const confirmUnarchiveTenant = async (tenant) => {
        if (await unarchiveTenant(tenant)) fetchTenants();
    };

    onMounted(() => {
        fetchTenants();
    });

    return {
        tenants,
        plansList,
        isLoading,
        isSubmitting,
        filters,
        statusOptions,
        planOptions,
        showCreateModal,
        showStatusModal,
        selectedTenant,
        createForm,
        statusForm,
        statusErrors,
        isSubmittingStatus,
        fetchTenants,
        updateSearch,
        updateStatusFilter,
        updatePlanFilter,
        openCreateModal,
        updateCreateField,
        submitCreateTenant,
        openStatusModal,
        updateStatusField,
        submitStatusChange,
        confirmDeleteTenant,
        confirmUnarchiveTenant,
        isArchiving,
        isUnarchiving,
        canManageTenants,
    };
}
