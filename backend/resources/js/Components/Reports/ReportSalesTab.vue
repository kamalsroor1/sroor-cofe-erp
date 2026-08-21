<script setup>
import MetricCard from '@/Components/Common/MetricCard.vue';
import { useMoney } from '@/Composables/useMoney';

defineProps({
    summary: {
        type: Object,
        required: true
    }
});

const { formatMoney } = useMoney();
</script>

<template>
    <div class="space-y-6 font-tajawal">
        <!-- 4 Top KPI Cards (Bento 2x2 on Mobile) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
            <MetricCard
                :title="$t('reports.total_issued_sales')"
                :value="formatMoney(summary.total_sales)"
                :currency="$t('common.currency')"
                :subtitle="$t('reports.approved_invoices_count', { count: summary.invoices_count })"
                variant="primary"
            />

            <MetricCard
                :title="$t('reports.cogs')"
                :value="formatMoney(summary.total_cogs)"
                :currency="$t('common.currency')"
                :subtitle="$t('reports.avg_invoice', { amount: formatMoney(summary.avg_invoice) })"
                variant="slate"
            />

            <MetricCard
                :title="$t('reports.gross_profit_trade')"
                :value="formatMoney(summary.gross_profit)"
                :currency="$t('common.currency')"
                :subtitle="`${$t('reports.gross_margin')}: ${summary.margin_percentage}%`"
                variant="success"
            />

            <MetricCard
                :title="$t('reports.net_profit_after_expenses')"
                :value="formatMoney(summary.net_profit)"
                :currency="$t('common.currency')"
                :subtitle="`${$t('reports.tab_expenses')}: ${formatMoney(summary.total_expenses)} ${$t('common.currency')}`"
                :variant="summary.net_profit >= 0 ? 'success' : 'danger'"
            />
        </div>

        <!-- Financial Statement Summary Table -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
            <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📑</span>
                <span>{{ $t('reports.pnl_breakdown') }}</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr class="py-2.5">
                            <td class="py-3 font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.gross_sales') }}</td>
                            <td class="py-3 font-mono font-bold text-slate-900 dark:text-white text-left">{{ formatMoney(summary.total_sales) }} {{ $t('common.currency') }}</td>
                        </tr>
                        <tr class="py-2.5">
                            <td class="py-3 font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.cogs_deducted') }}</td>
                            <td class="py-3 font-mono font-bold text-rose-600 dark:text-rose-400 text-left">- {{ formatMoney(summary.total_cogs) }} {{ $t('common.currency') }}</td>
                        </tr>
                        <tr class="py-2.5 bg-slate-50 dark:bg-slate-950/50">
                            <td class="py-3 font-black text-theme-primary">{{ $t('reports.gross_profit_trade') }}</td>
                            <td class="py-3 font-mono font-black text-theme-primary text-left">{{ formatMoney(summary.gross_profit) }} {{ $t('common.currency') }}</td>
                        </tr>
                        <tr class="py-2.5">
                            <td class="py-3 font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.operating_expenses_deducted') }}</td>
                            <td class="py-3 font-mono font-bold text-rose-600 dark:text-rose-400 text-left">- {{ formatMoney(summary.total_expenses) }} {{ $t('common.currency') }}</td>
                        </tr>
                        <tr class="py-2.5 bg-emerald-500/10 border-t-2 border-emerald-500/30">
                            <td class="py-3 font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ $t('reports.net_operating_profit') }}</td>
                            <td class="py-3 font-mono font-black text-emerald-600 dark:text-emerald-400 text-sm text-left">{{ formatMoney(summary.net_profit) }} {{ $t('common.currency') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
