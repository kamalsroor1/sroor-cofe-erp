<script setup>
import { useMoney } from '@/Composables/useMoney';

defineProps({
    summary: {
        type: Object,
        required: true
    },
    abcData: {
        type: Object,
        default: () => ({})
    }
});

defineEmits(['export-abc']);

const { formatMoney } = useMoney();
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <!-- Valuation Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-1 shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.stock_cost_valuation') }}</span>
                <div class="text-2xl font-black font-mono text-indigo-600 dark:text-indigo-300">
                    {{ formatMoney(summary.stock_cost_valuation) }} <span class="text-xs font-bold text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-1 shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.stock_selling_valuation') }}</span>
                <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">
                    {{ formatMoney(summary.stock_selling_valuation) }} <span class="text-xs font-bold text-theme-primary">{{ $t('common.currency') }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-1 shadow-xs">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.expected_stock_profit') }}</span>
                <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                    {{ formatMoney(summary.expected_stock_profit) }} <span class="text-xs font-bold text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                </div>
            </div>
        </div>

        <!-- ABC Analysis Section -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 space-y-4 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.abc_pareto_title') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $t('reports.abc_pareto_sub') }}</p>
                </div>

                <button
                    type="button"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center gap-1.5"
                    @click="$emit('export-abc')"
                >
                    <span>📥</span>
                    <span>{{ $t('reports.export_abc_excel') }}</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">{{ $t('reports.abc_class_a_title') }}</span>
                    <div class="text-lg font-black font-mono text-slate-900 dark:text-white">{{ $t('reports.items_count', { count: abcData?.category_a?.length || 0 }) }}</div>
                </div>

                <div class="bg-theme-light border border-theme-primary rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-black text-theme-primary">{{ $t('reports.abc_class_b_title') }}</span>
                    <div class="text-lg font-black font-mono text-slate-900 dark:text-white">{{ $t('reports.items_count', { count: abcData?.category_b?.length || 0 }) }}</div>
                </div>

                <div class="bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 space-y-1">
                    <span class="text-xs font-black text-slate-600 dark:text-slate-400">{{ $t('reports.abc_class_c_title') }}</span>
                    <div class="text-lg font-black font-mono text-slate-900 dark:text-white">{{ $t('reports.items_count', { count: abcData?.category_c?.length || 0 }) }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
