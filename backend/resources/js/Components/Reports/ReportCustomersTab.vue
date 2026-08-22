<script setup>
import { computed } from 'vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

defineProps({
    customerSales: {
        type: Array,
        default: () => []
    },
    totalCustomersDebt: {
        type: [Number, String],
        default: 0
    }
});

const { formatMoney } = useMoney();

const customerColumns = computed(() => [
    { key: 'name', label: trans('invoices.customer'), sortable: true },
    { key: 'phone', label: trans('contacts.phone'), mono: true },
    { key: 'total_invoices', label: trans('reports.invoices_count'), mono: true },
    { key: 'total_bought', label: trans('reports.total_bought'), mono: true },
    { key: 'total_paid', label: trans('reports.paid_in_period'), mono: true },
    { key: 'total_debt_in_period', label: trans('reports.remaining_in_period'), mono: true },
    { key: 'current_balance', label: trans('reports.cumulative_balance'), mono: true, align: 'left' },
]);
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.top_customers_sales') }}</h3>
            <div class="text-xs text-rose-600 dark:text-rose-400 font-bold">
                {{ $t('reports.all_customers_debt') }}: <span class="font-mono font-black">{{ formatMoney(totalCustomersDebt) }} {{ $t('common.currency') }}</span>
            </div>
        </div>

        <DataTable
            :columns="customerColumns"
            :rows="customerSales"
            :empty-title="$t('common.no_data_available')"
            empty-icon="👥"
        >
            <!-- Name -->
            <template #cell-name="{ row }">
                <span class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.name }}</span>
            </template>

            <!-- Phone -->
            <template #cell-phone="{ row }">
                <span class="font-mono text-slate-500 dark:text-slate-400">{{ row.phone || '—' }}</span>
            </template>

            <!-- Total Invoices -->
            <template #cell-total_invoices="{ row }">
                <span class="font-mono text-slate-600 dark:text-slate-300">{{ row.total_invoices }}</span>
            </template>

            <!-- Total Bought -->
            <template #cell-total_bought="{ row }">
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(row.total_bought) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Total Paid -->
            <template #cell-total_paid="{ row }">
                <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.total_paid) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Total Debt In Period -->
            <template #cell-total_debt_in_period="{ row }">
                <span class="font-mono text-rose-600 dark:text-rose-400">{{ formatMoney(row.total_debt_in_period) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Current Balance -->
            <template #cell-current_balance="{ row }">
                <span class="font-mono text-left font-black" :class="row.current_balance > 0 ? 'text-theme-primary' : 'text-slate-500 dark:text-slate-400'">
                    {{ formatMoney(row.current_balance) }} {{ $t('common.currency') }}
                </span>
            </template>

            <!-- Mobile Card Custom Slot -->
            <template #mobile-card="{ row }">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                        <div>
                            <div class="font-black text-xs text-slate-900 dark:text-white">{{ row.name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ row.phone || '—' }}</div>
                        </div>
                        <div class="text-left">
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.cumulative_balance') }}</span>
                            <span class="font-mono font-black text-xs" :class="row.current_balance > 0 ? 'text-theme-primary' : 'text-slate-500 dark:text-slate-400'">
                                {{ formatMoney(row.current_balance) }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.total_bought') }}</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(row.total_bought) }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.paid_in_period') }}</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.total_paid) }}</span>
                        </div>
                        <div class="text-left">
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.remaining_in_period') }}</span>
                            <span class="font-black text-rose-600 dark:text-rose-400">{{ formatMoney(row.total_debt_in_period) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>

