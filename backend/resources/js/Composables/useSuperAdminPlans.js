import { ref, computed, onMounted } from 'vue';
import api from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { usePlanEditor } from './usePlanEditor';

export function useSuperAdminPlans() {
    const centralAuth = useCentralAuthStore();

    const plans = ref([]);
    const featuresRegistry = ref([]);
    const addonsCatalog = ref([]);
    // Platform VAT percent ('14.000'); '0.000' hides the VAT-inclusive hints in the plan editor.
    const taxRate = ref('0.000');
    const isLoading = ref(true);
    const hasError = ref(false);

    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.plansManage));

    const fetchPlans = async () => {
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/super-admin/plans');
            const data = res.data?.data || {};
            plans.value = data.plans || [];
            featuresRegistry.value = data.features_registry || [];
            addonsCatalog.value = data.addons_catalog || [];
            taxRate.value = data.tax_rate ?? '0.000';
        } catch {
            hasError.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const editor = usePlanEditor({ registry: featuresRegistry, catalog: addonsCatalog, taxRate, onSaved: fetchPlans });

    onMounted(fetchPlans);

    return { plans, isLoading, hasError, canManage, fetchPlans, editor };
}
