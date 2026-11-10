import { ref, computed } from 'vue';
import api from '../Services/api';

/**
 * Public pricing catalog (GET /api/v1/public/plans): no auth, no tenant. Everything the landing
 * pages show (brand, prices, limits, features, founder offer) comes from here, nothing is hardcoded.
 */
export function usePublicPlans() {
    const catalog = ref(null);
    const isLoading = ref(false);
    const hasError = ref(false);

    const plans = computed(() => catalog.value?.plans || []);
    const addons = computed(() => catalog.value?.addons || []);
    const brand = computed(() => catalog.value?.brand || {});
    const founder = computed(() => catalog.value?.founder || { available: false });
    const currency = computed(() => catalog.value?.currency || '');
    const pricesExcludeVat = computed(() => catalog.value?.prices_exclude_vat === true);

    const load = async () => {
        isLoading.value = true;
        hasError.value = false;
        try {
            const res = await api.get('/public/plans');
            catalog.value = res.data?.data || null;
            hasError.value = !catalog.value;
        } catch {
            hasError.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    return { catalog, plans, addons, brand, founder, currency, pricesExcludeVat, isLoading, hasError, load };
}
