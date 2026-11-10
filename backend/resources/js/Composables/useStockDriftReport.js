import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';

const SEARCH_DEBOUNCE_MS = 300;

export function useStockDriftReport() {
    const { t } = useTrans();

    const items = ref([]);
    const isLoading = ref(false);
    const hasError = ref(false);
    const errorMessage = ref('');
    const search = ref('');
    const meta = reactive({
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: 0,
    });

    let searchTimer = null;

    const loadPage = async (page = 1) => {
        isLoading.value = true;
        hasError.value = false;
        errorMessage.value = '';
        try {
            const res = await api.get('/reports/stock-drift', {
                params: {
                    page,
                    per_page: meta.per_page,
                    search: search.value.trim() || undefined,
                },
            });
            items.value = res.data?.data || [];
            const m = res.data?.meta || {};
            meta.current_page = m.current_page || 1;
            meta.last_page = m.last_page || 1;
            meta.per_page = m.per_page || 25;
            meta.total = m.total ?? items.value.length;
        } catch (e) {
            hasError.value = true;
            errorMessage.value = e.userMessage || e.response?.data?.message || t('common.error_occurred');
        } finally {
            isLoading.value = false;
        }
    };

    const onSearchInput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadPage(1), SEARCH_DEBOUNCE_MS);
    };

    onMounted(() => loadPage(1));
    onBeforeUnmount(() => clearTimeout(searchTimer));

    return { items, isLoading, hasError, errorMessage, search, meta, loadPage, onSearchInput };
}
