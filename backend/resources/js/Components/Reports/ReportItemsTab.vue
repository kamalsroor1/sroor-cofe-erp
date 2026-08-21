<script setup>
import { computed } from 'vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

defineProps({
    itemProfits: {
        type: Array,
        default: () => []
    }
});

const { formatMoney } = useMoney();

const itemColumns = computed(() => [
    { key: 'name', label: trans('inventory.item_name'), sortable: true },
    { key: 'category', label: trans('expenses.category') },
    { key: 'total_qty', label: trans('invoices.quantity'), mono: true },
    { key: 'total_revenue', label: trans('reports.sales_summary'), mono: true },
    { key: 'total_cogs', label: trans('reports.cogs'), mono: true },
    { key: 'profit', label: trans('reports.gross_profit_trade'), mono: true },
    { key: 'margin', label: trans('reports.profit_margin'), mono: true, align: 'left' },
]);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.items_profit_detail') }}</h3>
            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $t('reports.items_sold_count', { count: itemProfits.length }) }}</span>
        </div>

        <DataTable
            :columns="itemColumns"
            :rows="itemProfits"
            :empty-title="$t('reports.no_data_available')"
            empty-icon="📊"
        >
            <!-- Name -->
            <template #cell-name="{ row }">
                <span class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.name }}</span>
            </template>

            <!-- Category -->
            <template #cell-category="{ row }">
                <span class="text-slate-500 dark:text-slate-400 font-tajawal">{{ row.category || $t('common.all') }}</span>
            </template>

            <!-- Quantity -->
            <template #cell-total_qty="{ row }">
                <span class="font-mono text-theme-primary font-bold">{{ row.total_qty }} {{ row.unit }}</span>
            </template>

            <!-- Revenue -->
            <template #cell-total_revenue="{ row }">
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(row.total_revenue) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- COGS -->
            <template #cell-total_cogs="{ row }">
                <span class="font-mono text-slate-500 dark:text-slate-400">{{ formatMoney(row.total_cogs) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Profit -->
            <template #cell-profit="{ row }">
                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.profit) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Margin -->
            <template #cell-margin="{ row }">
                <span class="font-mono text-left font-bold text-theme-primary">{{ row.margin }}%</span>
            </template>

            <!-- Mobile Card Custom Slot -->
            <template #mobile-card="{ row }">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                        <div>
                            <div class="font-black text-xs text-slate-900 dark:text-white">{{ row.name }}</div>
                            <div class="text-[10px] text-slate-400 font-tajawal">{{ row.category || $t('common.all') }}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-mono font-black text-xs">
                            {{ row.margin }}%
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('invoices.quantity') }}</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ row.total_qty }} {{ row.unit }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.sales_summary') }}</span>
                            <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(row.total_revenue) }}</span>
                        </div>
                        <div class="text-left">
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.gross_profit_trade') }}</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.profit) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>

