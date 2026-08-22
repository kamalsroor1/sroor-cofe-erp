<script setup>
import { computed } from 'vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

defineProps({
    storeBreakdown: {
        type: Array,
        default: () => []
    }
});

const { formatMoney } = useMoney();

const storeColumns = computed(() => [
    { key: 'name', label: trans('inventory.store'), sortable: true },
    { key: 'invoice_count', label: trans('reports.invoices_count'), mono: true },
    { key: 'total_sales', label: trans('reports.total_issued_sales'), mono: true },
    { key: 'total_paid', label: trans('contacts.paid_amount'), mono: true },
    { key: 'total_remaining', label: trans('contacts.remaining_amount'), mono: true },
    { key: 'gross_profit', label: trans('reports.gross_profit_trade'), mono: true },
    { key: 'margin', label: trans('reports.profit_margin'), mono: true },
    { key: 'share_pct', label: trans('reports.revenue_share'), mono: true, align: 'left' },
]);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <h3 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-3">{{ $t('reports.stores_comparison_title') }}</h3>

        <DataTable
            :columns="storeColumns"
            :rows="storeBreakdown"
            :empty-title="$t('common.no_data_available')"
            empty-icon="🏪"
        >
            <!-- Name -->
            <template #cell-name="{ row }">
                <span class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.name }}</span>
            </template>

            <!-- Invoice Count -->
            <template #cell-invoice_count="{ row }">
                <span class="font-mono text-slate-600 dark:text-slate-300">{{ row.invoice_count }}</span>
            </template>

            <!-- Total Sales -->
            <template #cell-total_sales="{ row }">
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(row.total_sales) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Total Paid -->
            <template #cell-total_paid="{ row }">
                <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.total_paid) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Total Remaining -->
            <template #cell-total_remaining="{ row }">
                <span class="font-mono text-rose-600 dark:text-rose-400">{{ formatMoney(row.total_remaining) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Gross Profit -->
            <template #cell-gross_profit="{ row }">
                <span class="font-mono font-bold text-theme-primary">{{ formatMoney(row.gross_profit) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Margin -->
            <template #cell-margin="{ row }">
                <span class="font-mono text-slate-600 dark:text-slate-300">{{ row.margin }}%</span>
            </template>

            <!-- Share Pct -->
            <template #cell-share_pct="{ row }">
                <span class="font-mono text-left font-black text-theme-primary">{{ row.share_pct }}%</span>
            </template>

            <!-- Mobile Card Custom Slot -->
            <template #mobile-card="{ row }">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                        <div>
                            <div class="font-black text-xs text-slate-900 dark:text-white">{{ row.name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ row.invoice_count }} {{ $t('invoices.title') }}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded-lg tab-theme-active font-mono font-black text-xs">
                            {{ row.share_pct }}%
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.total_issued_sales') }}</span>
                            <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(row.total_sales) }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.gross_profit_trade') }}</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.gross_profit) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs font-mono pt-1.5 border-t border-slate-200 dark:border-slate-800">
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $t('contacts.paid_amount') }}: {{ formatMoney(row.total_paid) }}</span>
                        <span class="text-rose-600 dark:text-rose-400 font-bold">{{ $t('contacts.remaining_amount') }}: {{ formatMoney(row.total_remaining) }}</span>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>

