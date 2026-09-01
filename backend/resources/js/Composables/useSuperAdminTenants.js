import { ref, computed, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';

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
        { value: 'active', label: t('super.status_active') },
        { value: 'trial', label: t('super.status_trial') },
        { value: 'suspended', label: t('super.status_suspended') },
    ]);

    const planOptions = computed(() => [
        { value: 'all', label: t('super.all_plans') },
        ...plansList.value.map(p => ({ value: p.id, label: p.name })),
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

    const statusForm = ref({
        status: 'active',
        extend_days: 0,
    });

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
        statusForm.value = {
            status: tenant.status || 'active',
            extend_days: 0,
        };
        showStatusModal.value = true;
    };

    const updateStatusField = (field, val) => {
        statusForm.value[field] = val;
    };

    const submitStatusChange = async () => {
        if (!selectedTenant.value) return;
        isSubmitting.value = true;
        try {
            await api.post(`/super-admin/tenants/${selectedTenant.value.id}/toggle-status`, statusForm.value);
            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('super.status_updated_msg'),
                timer: 1500,
                showConfirmButton: false,
            });
            showStatusModal.value = false;
            fetchTenants();
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('super.status_update_failed'),
                confirmButtonText: t('common.ok'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    const confirmDeleteTenant = async (tenant) => {
        const result = await DarkSwal.fire({
            title: t('super.delete_version_confirm_title'),
            text: tenant.name,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('common.yes'),
            cancelButtonText: t('common.cancel'),
        });

        if (result.isConfirmed) {
            try {
                await api.delete(`/super-admin/tenants/${tenant.id}`);
                DarkSwal.fire({
                    icon: 'success',
                    title: t('common.success'),
                    text: t('common.success'),
                    timer: 1500,
                    showConfirmButton: false,
                });
                fetchTenants();
            } catch (e) {
                DarkSwal.fire({
                    icon: 'error',
                    title: t('common.error'),
                    text: e.response?.data?.message || t('common.error'),
                    confirmButtonText: t('common.ok'),
                });
            }
        }
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
    };
}
