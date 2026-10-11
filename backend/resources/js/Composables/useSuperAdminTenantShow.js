import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';
import { useTenantStatusForm } from './useTenantStatusForm';
import { ARCHIVABLE_STATUSES, isArchiveInProgress, useTenantArchiveActions } from './useSuperAdminTenants';

// The archive block may sit on the tenant or next to it (data.archive); the UI reads tenant.archive.
const withArchiveBlock = (data) =>
    data.tenant && !data.tenant.archive && data.archive ? { ...data.tenant, archive: data.archive } : data.tenant;

/** While an archive job is queued/running the page re-reads the tenant this often. */
const ARCHIVE_POLL_MS = 10000;

/**
 * The tenant's OPS-9 archive block (GET /super-admin/tenants/{id} -> data.tenant.archive) in one shape for the UI.
 * Returns null when the server sent no archive block.
 */
export const normalizeArchiveBlock = (archive) => {
    if (!archive || typeof archive !== 'object') return null;
    const backup = archive.final_backup && typeof archive.final_backup === 'object' ? archive.final_backup : null;
    return {
        state: archive.state ?? null,
        requestedAt: archive.requested_at ?? null,
        errorCode: archive.error_code ?? null,
        archivedAt: archive.archived_at ?? null,
        finalBackup: backup
            ? {
                  id: backup.id ?? null,
                  createdAt: backup.created_at ?? null,
                  disk: backup.disk ?? null,
                  sha256Prefix: backup.sha256_prefix ?? null,
              }
            : null,
        purgeEligibleAt: archive.purge_eligible_at ?? null,
        purgedAt: archive.purged_at ?? null,
    };
};

export function useSuperAdminTenantShow() {
    const { t } = useTrans();
    const route = useRoute();
    const router = useRouter();

    const tenantId = route.params.id;
    const tenant = ref(null);
    const stats = ref({});
    const allFeatures = ref([]);
    const globalUnitsList = ref([]);
    const tenantAllowedUnits = ref([]);
    const customTenantUnit = ref('');

    const isLoading = ref(true);
    const isSavingUnits = ref(false);
    const isImpersonating = ref(false);
    const isMigrating = ref(false);
    const showStatusModal = ref(false);
    const {
        statusForm,
        statusErrors,
        isSubmittingStatus: isUpdatingStatus,
        resetStatusForm,
        updateStatusField,
        submitStatus,
    } = useTenantStatusForm();
    const { isArchiving, isUnarchiving, canManageTenants, archiveTenant, unarchiveTenant } = useTenantArchiveActions();

    const archiveInfo = computed(() => normalizeArchiveBlock(tenant.value?.archive));
    const archiveStateKey = computed(() =>
        archiveInfo.value?.state ? `tenant_archive.states.${archiveInfo.value.state}` : null
    );
    const isArchived = computed(() => tenant.value?.status === 'archived');
    const isPurged = computed(() => !!archiveInfo.value?.purgedAt);
    const canArchive = computed(
        () => !!tenant.value && ARCHIVABLE_STATUSES.includes(tenant.value.status) && !isArchiveInProgress(tenant.value)
    );
    const canUnarchive = computed(() => isArchived.value && !isPurged.value);
    const archiveInProgress = computed(() => isArchiveInProgress(tenant.value));
    // The panel only appears once the tenant has an archive history (request, archive or purge).
    const showArchivePanel = computed(
        () =>
            isArchived.value ||
            !!(archiveInfo.value?.state || archiveInfo.value?.archivedAt || archiveInfo.value?.purgedAt)
    );

    let archivePollTimer = null;
    const stopArchivePolling = () => {
        if (archivePollTimer) clearInterval(archivePollTimer);
        archivePollTimer = null;
    };

    // Background re-read while the archive job runs (no skeleton, no redirect on a transient error).
    const refreshArchiveState = async () => {
        try {
            const res = await api.get(`/super-admin/tenants/${tenantId}`);
            const data = res.data?.data;
            if (data?.tenant) tenant.value = withArchiveBlock(data);
        } catch {
            // keep the last known state; the next tick retries
        }
        if (!isArchiveInProgress(tenant.value)) stopArchivePolling();
    };

    const startArchivePolling = () => {
        if (archivePollTimer || !isArchiveInProgress(tenant.value)) return;
        archivePollTimer = setInterval(refreshArchiveState, ARCHIVE_POLL_MS);
    };

    const fetchTenantDetails = async () => {
        isLoading.value = true;
        try {
            const res = await api.get(`/super-admin/tenants/${tenantId}`);
            const data = res.data?.data;
            if (data) {
                tenant.value = withArchiveBlock(data);
                stats.value = data.stats || {};
                allFeatures.value = data.features || [];
                // SETG-10: both lists come from the server (it owns the defaults).
                globalUnitsList.value = Array.isArray(data.global_units) ? data.global_units : [];
                tenantAllowedUnits.value = Array.isArray(data.allowed_units) ? data.allowed_units : [];
                startArchivePolling();
            }
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: t('super.tenant_create_failed'),
            });
            router.push('/super-admin/tenants');
        } finally {
            isLoading.value = false;
        }
    };

    const toggleFeature = async (featureKey) => {
        try {
            await api.post(`/super-admin/tenants/${tenantId}/override-feature`, {
                feature_key: featureKey,
            });
            const current = tenant.value.enabled_features || [];
            if (current.includes(featureKey)) {
                tenant.value.enabled_features = current.filter((f) => f !== featureKey);
            } else {
                tenant.value.enabled_features = [...current, featureKey];
            }
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: t('common.error'),
            });
        }
    };

    const addTenantUnit = (unit) => {
        if (!tenantAllowedUnits.value.includes(unit)) {
            tenantAllowedUnits.value.push(unit);
        }
    };

    const addCustomUnitDirect = () => {
        const u = customTenantUnit.value.trim();
        if (!u) return;
        if (!tenantAllowedUnits.value.includes(u)) {
            tenantAllowedUnits.value.push(u);
        }
        customTenantUnit.value = '';
    };

    const removeTenantUnit = (idx) => {
        tenantAllowedUnits.value.splice(idx, 1);
    };

    const saveTenantUnits = async () => {
        if (tenantAllowedUnits.value.length === 0) {
            DarkSwal.fire({
                icon: 'warning',
                title: t('common.error'),
                text: t('super.at_least_one_unit_required'),
            });
            return;
        }

        isSavingUnits.value = true;
        try {
            await api.post(`/super-admin/tenants/${tenantId}/update-units`, {
                units: tenantAllowedUnits.value,
            });

            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('super.units_saved_success'),
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.units_save_failed'),
            });
        } finally {
            isSavingUnits.value = false;
        }
    };

    const runMigrations = async () => {
        isMigrating.value = true;
        try {
            const res = await api.post(`/super-admin/tenants/${tenantId}/run-migrations`);
            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: res.data?.message || t('super.migrations_completed_success'),
            });
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.migrations_failed'),
            });
        } finally {
            isMigrating.value = false;
        }
    };

    const impersonateTenant = () => {
        if (tenant.value?.domain) {
            window.open(`http://${tenant.value.domain}`, '_blank');
        }
    };

    const openStatusModal = () => {
        resetStatusForm(tenant.value?.status || null);
        showStatusModal.value = true;
    };

    const updateStatusAndPlan = async () => {
        const updated = await submitStatus(tenantId);
        if (!updated) return;
        if (tenant.value) {
            tenant.value.status = updated.status;
            if ('trial_ends_at' in updated) tenant.value.trial_ends_at = updated.trial_ends_at;
            if ('subscription_ends_at' in updated) tenant.value.subscription_ends_at = updated.subscription_ends_at;
        }
        showStatusModal.value = false;
    };

    // OPS-9: "delete" queues an archive (202). The tenant stays on this page so the progress is visible.
    const deleteTenant = async () => {
        const queued = await archiveTenant(tenant.value);
        if (!queued || !tenant.value) return;
        if (queued.status) tenant.value.status = queued.status;
        if (queued.archive) tenant.value.archive = { ...(tenant.value.archive || {}), ...queued.archive };
        startArchivePolling();
    };

    const restoreArchivedTenant = async () => {
        const updated = await unarchiveTenant(tenant.value);
        if (updated) fetchTenantDetails();
    };

    onMounted(() => {
        fetchTenantDetails();
    });

    onUnmounted(stopArchivePolling);

    return {
        tenant,
        stats,
        allFeatures,
        globalUnitsList,
        tenantAllowedUnits,
        customTenantUnit,
        isLoading,
        isSavingUnits,
        isImpersonating,
        isMigrating,
        showStatusModal,
        isUpdatingStatus,
        statusForm,
        statusErrors,
        toggleFeature,
        addTenantUnit,
        addCustomUnitDirect,
        removeTenantUnit,
        saveTenantUnits,
        runMigrations,
        impersonateTenant,
        openStatusModal,
        updateStatusAndPlan,
        updateStatusField,
        deleteTenant,
        restoreArchivedTenant,
        archiveInfo,
        archiveStateKey,
        isArchived,
        isPurged,
        canArchive,
        canUnarchive,
        archiveInProgress,
        showArchivePanel,
        isArchiving,
        isUnarchiving,
        canManageTenants,
    };
}
