import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useTrans } from './useTrans';
import DarkSwal from '../helpers/alert';

export function useSuperAdminPlans() {
    const { t } = useTrans();

    const plans = ref([]);
    const isLoading = ref(false);
    const isSubmitting = ref(false);
    const showEditModal = ref(false);
    const selectedPlan = ref(null);

    const editForm = ref({
        name: '',
        price_monthly: 0,
        price_yearly: 0,
        max_users: 1,
        max_stores: 1,
        max_items: 100,
        max_invoices_per_month: 1000,
        is_active: true,
        is_popular: false,
        features: {},
    });

    const fetchPlans = async () => {
        isLoading.value = true;
        try {
            const res = await api.get('/super-admin/plans');
            plans.value = res.data?.data?.plans || res.data?.plans || [];
        } catch (e) {
            console.error('Failed to load plans:', e);
        } finally {
            isLoading.value = false;
        }
    };

    const openEditModal = (plan) => {
        selectedPlan.value = plan;
        editForm.value = {
            name: plan.name,
            price_monthly: plan.price_monthly,
            price_yearly: plan.price_yearly,
            max_users: plan.max_users,
            max_stores: plan.max_stores,
            max_items: plan.max_items,
            max_invoices_per_month: plan.max_invoices_per_month,
            is_active: !!plan.is_active,
            is_popular: !!plan.is_popular,
            features: plan.features || {},
        };
        showEditModal.value = true;
    };

    const updateEditField = (field, val) => {
        editForm.value[field] = val;
    };

    const submitEditPlan = async () => {
        if (!selectedPlan.value) return;
        isSubmitting.value = true;
        try {
            await api.put(`/super-admin/plans/${selectedPlan.value.id}`, editForm.value);
            DarkSwal.fire({
                icon: 'success',
                title: t('common.success'),
                text: t('super.plan_updated_success', { name: selectedPlan.value.name }),
                timer: 1500,
                showConfirmButton: false,
            });
            showEditModal.value = false;
            fetchPlans();
        } catch (e) {
            DarkSwal.fire({
                icon: 'error',
                title: t('common.error'),
                text: e.response?.data?.message || t('common.error'),
            });
        } finally {
            isSubmitting.value = false;
        }
    };

    onMounted(() => {
        fetchPlans();
    });

    return {
        plans,
        isLoading,
        isSubmitting,
        showEditModal,
        selectedPlan,
        editForm,
        fetchPlans,
        openEditModal,
        updateEditField,
        submitEditPlan,
    };
}
