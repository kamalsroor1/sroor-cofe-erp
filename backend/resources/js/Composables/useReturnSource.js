import { ref, computed } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { dMul, dSum, dCmp, normalize } from '../helpers/decimal';

/**
 * BE-01: pick the invoice (sales) / purchase (purchase) a return is booked against, then the
 * lines of it that still have a returnable quantity. Item and price come from the server.
 */
export function useReturnSource(returnType) {
    const { t } = useTrans();
    const sources = ref([]);
    const search = ref('');
    const isLoadingSources = ref(false);
    const isLoadingLines = ref(false);
    const selected = ref(null);
    const lines = ref([]);
    const errorMessage = ref('');
    // Request tokens: a slow response of an older search/selection must not overwrite a newer one.
    let sourcesToken = 0;
    let linesToken = 0;

    const loadSources = async () => {
        const token = ++sourcesToken;
        isLoadingSources.value = true;
        errorMessage.value = '';
        try {
            const res = await api.get(`/return-sources/${returnType.value}`, {
                params: { search: search.value || undefined, per_page: 15 },
            });
            if (token !== sourcesToken) return;
            sources.value = res.data?.data || [];
        } catch (error) {
            if (token !== sourcesToken) return;
            sources.value = [];
            errorMessage.value = error.response?.data?.message || t('returns.source_load_failed');
        } finally {
            if (token === sourcesToken) isLoadingSources.value = false;
        }
    };

    const selectSource = async (source) => {
        const token = ++linesToken;
        isLoadingLines.value = true;
        errorMessage.value = '';
        try {
            const res = await api.get(`/return-sources/${returnType.value}/${source.id}`);
            if (token !== linesToken) return;
            selected.value = res.data?.data || null;
            lines.value = (selected.value?.lines || [])
                .filter((l) => dCmp(l.remaining_quantity, '0') > 0)
                .map((l) => ({ ...l, return_quantity: '' }));
        } catch (error) {
            if (token !== linesToken) return;
            selected.value = null;
            lines.value = [];
            errorMessage.value = error.response?.data?.message || t('returns.source_load_failed');
        } finally {
            if (token === linesToken) isLoadingLines.value = false;
        }
    };

    const clearSource = () => {
        linesToken++;
        isLoadingLines.value = false;
        selected.value = null;
        lines.value = [];
    };

    const setQuantity = (line, value) => {
        const qty = normalize(value);
        if (dCmp(qty, '0') <= 0) {
            line.return_quantity = '';
        } else if (dCmp(qty, line.remaining_quantity) > 0) {
            line.return_quantity = line.remaining_quantity;
        } else {
            line.return_quantity = String(value);
        }
    };

    const chosenLines = computed(() => lines.value.filter((l) => dCmp(normalize(l.return_quantity), '0') > 0));

    const total = computed(() => dSum(chosenLines.value.map((l) => dMul(l.return_quantity, l.unit_price))));

    const payloadItems = () =>
        chosenLines.value.map((l) => ({
            [returnType.value === 'sales_return' ? 'invoice_item_id' : 'purchase_item_id']: l.id,
            quantity: l.return_quantity,
        }));

    return {
        sources,
        search,
        isLoadingSources,
        isLoadingLines,
        selected,
        lines,
        errorMessage,
        chosenLines,
        total,
        loadSources,
        selectSource,
        clearSource,
        setQuantity,
        payloadItems,
    };
}
