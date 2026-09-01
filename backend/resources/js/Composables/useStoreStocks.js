import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';
import { useTrans } from './useTrans';

export function useStoreStocks() {
    const route = useRoute();
    const { t } = useTrans();

    const stores = ref([]);
    const stocks = ref([]);
    const selectedStoreId = ref(parseInt(route.query.store_id || '1', 10));
    const searchQuery = ref('');
    const stockStatus = ref('all');
    const isLoading = ref(false);

    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });

    let debounceTimeout = null;

    const storeOptions = computed(() => {
        return stores.value.map(s => ({
            value: s.id,
            label: `${s.name} ${s.is_main ? `(${t('inventory.main_store')})` : ''}`,
        }));
    });

    const fetchStores = async () => {
        try {
            const response = await api.get('/stores');
            stores.value = response.data?.stores || response.data?.data || [];
            if (!selectedStoreId.value && stores.value.length > 0) {
                selectedStoreId.value = stores.value[0].id;
            }
        } catch (e) {
            console.error('Failed to load stores:', e);
        }
    };

    const fetchStocks = async (page = 1) => {
        if (!selectedStoreId.value) return;

        isLoading.value = true;
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
        } catch (error) {
            console.error('Failed to load store stocks:', error);
        } finally {
            isLoading.value = false;
        }
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
        await fetchStores();
        await fetchStocks(1);
    });

    return {
        stores,
        stocks,
        selectedStoreId,
        searchQuery,
        stockStatus,
        isLoading,
        pagination,
        storeOptions,
        fetchStores,
        fetchStocks,
        debounceSearch,
        setStockStatus,
    };
}
