import { ref } from 'vue';
import api from '../../Services/api';
import { useTrans } from '../useTrans';
import { usePosBusinessDate } from './usePosBusinessDate';

const PAGE_SIZE = 20;

/**
 * POS-11: today's latest invoices of the active store (newest first) for the "Recent sales" drawer.
 * Uses the invoices index, which is already store-scoped by X-Store-Id.
 */
export function usePosRecentSales() {
    const { t } = useTrans();
    const { refreshBusinessDate } = usePosBusinessDate();
    const show = ref(false);
    const sales = ref([]);
    const search = ref('');
    const isLoading = ref(false);
    const errorMessage = ref('');
    let token = 0;
    let debounce = null;

    let businessDate = null;

    const load = async () => {
        const mine = ++token;
        isLoading.value = true;
        errorMessage.value = '';
        try {
            const today = businessDate;
            const res = await api.get('/invoices', {
                params: {
                    from_date: today || undefined,
                    to_date: today || undefined,
                    per_page: PAGE_SIZE,
                    search: search.value.trim() || undefined,
                },
            });
            if (mine !== token) return;
            sales.value = res.data?.data || [];
        } catch (error) {
            if (mine !== token) return;
            sales.value = [];
            errorMessage.value = error.response?.data?.message || t('pos.recent_sales_load_failed');
        } finally {
            if (mine === token) isLoading.value = false;
        }
    };

    const open = async () => {
        show.value = true;
        isLoading.value = true;
        businessDate = await refreshBusinessDate();
        await load();
    };

    const close = () => {
        show.value = false;
    };

    const onSearch = (value) => {
        search.value = value;
        clearTimeout(debounce);
        debounce = setTimeout(load, 300);
    };

    return { show, sales, search, isLoading, errorMessage, open, close, load, onSearch };
}
