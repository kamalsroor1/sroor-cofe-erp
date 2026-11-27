import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';
import { useTrans } from './useTrans';
import { useLoadError } from './useLoadError';

export function useStoreStocks() {
    const route = useRoute();
    const authStore = useAuthStore();
    const { t } = useTrans();
    const { error, errorMessage, statusCode: errorStatusCode, canRetry, clearError, setError } = useLoadError();

    const stores = ref([]);
    const stocks = ref([]);
    const parsedQueryStoreId = Number(route.query.store_id);
    const selectedStoreId = ref(
        Number.isInteger(parsedQueryStoreId) && parsedQueryStoreId > 0 ? parsedQueryStoreId : null
    );
    const searchQuery = ref('');
    const stockStatus = ref('all');
    const storesLoading = ref(true);
    const isLoading = ref(true);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });

    let debounceTimeout = null;

    const storeOptions = computed(() => {
        return stores.value.map((s) => ({
            value: Number(s.id),
            label: s.is_main ? `${s.name} (${t('inventory.main_store')})` : s.name,
        }));
    });

    const resolveSelectedStoreId = (loadedStores, activeStoreFromApi = null) => {
        if (!Array.isArray(loadedStores) || loadedStores.length === 0) return null;
        const hasId = (candidate) => {
            const id = Number(candidate);
            return Number.isInteger(id) && loadedStores.some((s) => Number(s.id) === id);
        };
        if (hasId(selectedStoreId.value)) return Number(selectedStoreId.value);
        if (hasId(activeStoreFromApi?.id)) return Number(activeStoreFromApi.id);
        if (hasId(authStore.activeStoreId)) return Number(authStore.activeStoreId);
        const main = loadedStores.find((s) => s.is_main);
        return Number((main || loadedStores[0]).id);
    };

    const fetchStores = async () => {
        storesLoading.value = true;
        clearError();
        try {
            const response = await api.get('/stores');
            stores.value = response.data?.stores || response.data?.data || [];
            selectedStoreId.value = resolveSelectedStoreId(stores.value, response.data?.active_store);
            return true;
        } catch (e) {
            setError(e);
            isLoading.value = false;
            console.error('Failed to load stores:', e);
            return false;
        } finally {
            storesLoading.value = false;
        }
    };

    const fetchStocks = async (page = 1) => {
        if (!selectedStoreId.value) {
            stocks.value = [];
            isLoading.value = false;
            return;
        }

        isLoading.value = true;
        clearError();
        try {
            const response = await api.get('/stores/stocks', {
                params: {
                    store_id: selectedStoreId.value,
                    search: searchQuery.value || undefined,
                    stock_status: stockStatus.value !== 'all' ? stockStatus.value : undefined,
                    page: page,
                    per_page: 20,
                },
            });
            stocks.value = response.data?.data || [];
            pagination.value = response.data?.meta || {
                current_page: page,
                last_page: 1,
                per_page: 20,
                total: stocks.value.length,
            };
        } catch (err) {
            setError(err);
            console.error('Failed to load store stocks:', err);
        } finally {
            isLoading.value = false;
        }
    };

    const onStoreChange = () => {
        stocks.value = [];
        fetchStocks(1);
    };

    const retry = async () => {
        if (stores.value.length === 0) {
            isLoading.value = true;
            const ok = await fetchStores();
            if (ok) await fetchStocks(1);
            return;
        }
        await fetchStocks(pagination.value?.current_page || 1);
    };

    const debounceSearch = () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetchStocks(1);
        }, 300);
    };

    const setStockStatus = (status) => {
        stockStatus.value = status;
        fetchStocks(1);
    };

    onMounted(async () => {
        const ok = await fetchStores();
        if (ok) {
            await fetchStocks(1);
        }
    });

    return {
        error,
        errorMessage,
        errorStatusCode,
        canRetry,
        stores,
        stocks,
        selectedStoreId,
        searchQuery,
        stockStatus,
        storesLoading,
        isLoading,
        pagination,
        storeOptions,
        fetchStores,
        fetchStocks,
        onStoreChange,
        retry,
        debounceSearch,
        setStockStatus,
    };
}
