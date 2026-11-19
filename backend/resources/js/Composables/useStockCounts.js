import { ref, watch } from 'vue';
import api from '../Services/api';
import { trans } from '../helpers/trans';

/**
 * SHOP-02: the stocktake sessions list of the active branch, plus starting a new session.
 */
export function useStockCounts() {
    const counts = ref([]);
    const categories = ref([]);
    const status = ref('all');
    const search = ref('');
    const isLoading = ref(true);
    const isStarting = ref(false);
    const error = ref('');
    const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

    let searchTimer = null;

    const fetchCounts = async (page = 1) => {
        isLoading.value = true;
        error.value = '';
        try {
            const res = await api.get('/stock-counts', {
                params: {
                    page,
                    per_page: 15,
                    status: status.value !== 'all' ? status.value : undefined,
                    search: search.value || undefined,
                },
            });
            counts.value = res.data?.data || [];
            pagination.value = res.data?.meta || pagination.value;
        } catch (err) {
            error.value = err.userMessage || err.message || trans('common.error_occurred');
        } finally {
            isLoading.value = false;
        }
    };

    const fetchCategories = async () => {
        try {
            const res = await api.get('/categories');
            categories.value = res.data?.data || [];
        } catch {
            // Categories are optional here: the count can always cover every item.
            categories.value = [];
        }
    };

    /** Starts a session in the active branch; resolves to the new session or null. */
    const startCount = async (payload) => {
        isStarting.value = true;
        try {
            const res = await api.post('/stock-counts', payload);
            return res.data?.data || null;
        } finally {
            isStarting.value = false;
        }
    };

    watch(status, () => fetchCounts(1));
    watch(search, () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => fetchCounts(1), 300);
    });

    return {
        counts,
        categories,
        status,
        search,
        isLoading,
        isStarting,
        error,
        pagination,
        fetchCounts,
        fetchCategories,
        startCount,
    };
}
