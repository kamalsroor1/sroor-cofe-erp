import { ref } from 'vue';
import api from '../Services/api';
import { trans } from '../helpers/trans';

/**
 * SHOP-02: data of the A4 prints: the count sheet (session + lines) or the variance report.
 */
export function useStockCountPrint() {
    const count = ref(null);
    const variance = ref(null);
    const isLoading = ref(true);
    const error = ref('');

    const load = async (id, type) => {
        isLoading.value = true;
        error.value = '';
        try {
            if (type === 'variance') {
                const res = await api.get(`/stock-counts/${id}/variance`);
                variance.value = res.data?.data || null;
                count.value = variance.value?.count || null;
            } else {
                const res = await api.get(`/stock-counts/${id}`);
                count.value = res.data?.data || null;
            }
        } catch (err) {
            error.value = err.userMessage || err.message || trans('stock_counts.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    return { count, variance, isLoading, error, load };
}
