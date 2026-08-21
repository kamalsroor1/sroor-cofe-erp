<script setup>
import { useMoney } from '@/Composables/useMoney';

defineProps({
    expensesBreakdown: {
        type: Array,
        default: () => []
    },
    totalExpenses: {
        type: [Number, String],
        default: 0
    }
});

const { formatMoney } = useMoney();
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 space-y-4 shadow-xs font-tajawal">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.expenses_by_category') }}</h3>
            <div class="text-xs text-rose-600 dark:text-rose-400 font-bold">
                {{ $t('reports.tab_expenses') }}: <span class="font-mono font-black">{{ formatMoney(totalExpenses) }} {{ $t('common.currency') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="(exp, eIdx) in expensesBreakdown"
                :key="eIdx"
                class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-2"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-900 dark:text-white">{{ exp.category || $t('expenses.cc_operational') }}</span>
                    <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">{{ $t('reports.vouchers_count', { count: exp.count }) }}</span>
                </div>
                <div class="text-xl font-black font-mono text-rose-600 dark:text-rose-400">
                    {{ formatMoney(exp.amount) }} <span class="text-xs text-slate-400 font-normal">{{ $t('common.currency') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
