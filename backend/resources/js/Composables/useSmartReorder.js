import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useTrans } from './useTrans';

export function useSmartReorder() {
    const router = useRouter();
    const { t } = useTrans();

    const suggestions = ref([]);
    const metrics = ref({
        critical_count: 0,
        warning_count: 0,
        safe_count: 0,
        total_estimated_cost: 0,
    });

    const analysisDays = ref(14);
    const targetCoverDays = ref(15);
    const selectedUrgency = ref('all');
    const searchQuery = ref('');
    const isLoading = ref(false);
    const selectedItems = ref([]);

    let debounceTimer = null;

    const analysisDaysOptions = computed(() => [
        { value: 7, label: `${t('purchases.consumption_analysis')} ${t('purchases.last_7_days')}` },
        { value: 14, label: `${t('purchases.consumption_analysis')} ${t('purchases.last_14_days')}` },
        { value: 30, label: `${t('purchases.consumption_analysis')} ${t('purchases.last_30_days')}` },
    ]);

    const targetCoverOptions = computed(() => [
        { value: 7, label: `${t('purchases.target_period')} ${t('purchases.cover_7_days')}` },
        { value: 15, label: `${t('purchases.target_period')} ${t('purchases.cover_15_days')}` },
        { value: 30, label: `${t('purchases.target_period')} ${t('purchases.cover_30_days')}` },
    ]);

    const urgencyOptions = computed(() => [
        { value: 'all', label: t('purchases.urgency_all') },
        { value: 'critical', label: t('purchases.urgency_critical_only') },
        { value: 'warning', label: t('purchases.urgency_warning_only') },
        { value: 'safe', label: t('purchases.urgency_safe_only') },
    ]);

    const selectedIds = computed(() => selectedItems.value.map(it => it.id));

    const isAllSelected = computed(() => {
        return suggestions.value.length > 0 && selectedItems.value.length === suggestions.value.length;
    });

    const toggleSelectAll = () => {
        if (isAllSelected.value) {
            selectedItems.value = [];
        } else {
            selectedItems.value = [...suggestions.value];
        }
    };

    const toggleItem = (item) => {
        const idx = selectedItems.value.findIndex(it => it.id === item.id);
        if (idx > -1) {
            selectedItems.value.splice(idx, 1);
        } else {
            selectedItems.value.push(item);
        }
    };

    const fetchSuggestions = async () => {
        isLoading.value = true;
        try {
            const response = await api.get('/purchases/smart-reorder', {
                params: {
                    analysis_days: analysisDays.value,
                    target_cover_days: targetCoverDays.value,
                    urgency: selectedUrgency.value !== 'all' ? selectedUrgency.value : undefined,
                    search: searchQuery.value || undefined,
                },
            });
            const data = response.data?.data;
            if (data) {
                suggestions.value = data.suggestions || [];
                metrics.value = {
                    critical_count: data.critical_count || 0,
                    warning_count: data.warning_count || 0,
                    safe_count: data.safe_count || 0,
                    total_estimated_cost: data.total_estimated_cost || 0,
                };
            }
        } catch (error) {
            console.error('Failed to load smart reorder suggestions:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const debounceFetch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchSuggestions, 300);
    };

    const exportToPurchaseOrder = () => {
        if (selectedItems.value.length === 0) return;
        const prefill = JSON.stringify(selectedItems.value.map(it => ({
            item_id: it.id,
            quantity: it.suggested_reorder_qty,
            cost_price: it.cost_price,
        })));
        router.push({
            path: '/purchases/create',
            query: { prefill },
        });
    };

    onMounted(fetchSuggestions);

    return {
        suggestions,
        metrics,
        analysisDays,
        targetCoverDays,
        selectedUrgency,
        searchQuery,
        isLoading,
        selectedItems,
        selectedIds,
        isAllSelected,
        analysisDaysOptions,
        targetCoverOptions,
        urgencyOptions,
        toggleSelectAll,
        toggleItem,
        fetchSuggestions,
        debounceFetch,
        exportToPurchaseOrder,
    };
}
