import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';
import { useTenantStatusForm } from './useTenantStatusForm';

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

    const fetchTenantDetails = async () => {
        isLoading.value = true;
        try {
            const res = await api.get(`/super-admin/tenants/${tenantId}`);
            const data = res.data?.data;
            if (data) {
                tenant.value = data.tenant;
                stats.value = data.stats || {};
                allFeatures.value = data.features || [];
                // SETG-10: both lists come from the server (it owns the defaults).
                globalUnitsList.value = Array.isArray(data.global_units) ? data.global_units : [];
                tenantAllowedUnits.value = Array.isArray(data.allowed_units) ? data.allowed_units : [];
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

    const deleteTenant = async () => {
        const result = await DarkSwal.fire({
            title: t('super.delete_tenant_confirm_title'),
            text: t('super.delete_tenant_confirm_desc', { name: tenant.value?.name || '' }),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: t('super.delete_tenant_confirm_btn'),
            cancelButtonText: t('common.cancel'),
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/super-admin/tenants/${tenantId}`);
                DarkSwal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('common.success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                router.push('/super-admin/tenants');
            } catch (e) {
                DarkSwal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: e.response?.data?.message || t('common.error'),
                });
            }
        }
    };

    onMounted(() => {
        fetchTenantDetails();
    });

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
    };
}
