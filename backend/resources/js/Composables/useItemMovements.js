import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';
import { useTrans } from './useTrans';

export function useItemMovements() {
    const route = useRoute();
    const itemId = route.params.id;
    const { t } = useTrans();

    const item = ref(null);
    const movements = ref([]);
    const stats = ref({
        total_in: 0,
        total_out: 0,
        net_movement: 0,
        current_scope_stock: 0,
    });

    const dateFrom = ref('');
    const dateTo = ref('');
    const activePreset = ref('all');
    const isLoading = ref(false);

    const formatMovementLabel = (type) => {
        const map = {
            purchase_in: `🚛 ${t('inventory.movement_purchase')}`,
            sales_out: `🛒 ${t('inventory.movement_sale')}`,
            transfer_in: `📥 ${t('inventory.movement_transfer_in')}`,
            transfer_out: `📤 ${t('inventory.movement_transfer_out')}`,
            sales_return_in: `↩️ ${t('inventory.movement_sale_return')}`,
            purchase_return_out: `↪️ ${t('inventory.movement_purchase_return')}`,
            stock_adjustment_in: `➕ ${t('inventory.movement_adjustment')}`,
            stock_adjustment_out: `➖ ${t('inventory.movement_adjustment')}`,
            cancellation_in: `🚫 ${t('invoices.cancelled_badge')}`,
            waste_out: `🗑️ ${t('inventory.movement_waste')}`,
            stock_deposit_in: `📦 ${t('inventory.movement_initial')}`,
        };
        return map[type] || type;
    };

    const isPositiveMovement = (type) => {
        const positive = ['purchase_in', 'stock_deposit_in', 'stock_adjustment_in', 'cancellation_in', 'transfer_in', 'sales_return_in'];
        return positive.includes(type);
    };

    const getMovementBadge = (type) => {
        return isPositiveMovement(type)
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
            : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400';
    };

    const applyPreset = (preset) => {
        activePreset.value = preset;
        const now = new Date();
        const formatDate = (d) => d.toISOString().split('T')[0];

        if (preset === 'today') {
            dateFrom.value = formatDate(now);
            dateTo.value = formatDate(now);
        } else if (preset === 'this_month') {
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            dateFrom.value = formatDate(start);
            dateTo.value = formatDate(end);
        } else if (preset === 'all') {
            dateFrom.value = '';
            dateTo.value = '';
        }
        fetchMovements();
    };

    const fetchMovements = async () => {
        isLoading.value = true;
        try {
            const response = await api.get(`/items/${itemId}/movements`, {
                params: {
                    from_date: dateFrom.value || undefined,
                    to_date: dateTo.value || undefined,
                },
            });
            const data = response.data?.data;
            if (data) {
                item.value = data.item;
                movements.value = data.data || [];
                stats.value = data.stats || {};
            }
        } catch (error) {
            console.error('Failed to load item movements:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const printReport = () => {
        window.print();
    };

    onMounted(fetchMovements);

    return {
        itemId,
        item,
        movements,
        stats,
        dateFrom,
        dateTo,
        activePreset,
        isLoading,
        formatMovementLabel,
        isPositiveMovement,
        getMovementBadge,
        applyPreset,
        fetchMovements,
        printReport,
    };
}
