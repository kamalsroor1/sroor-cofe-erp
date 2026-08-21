<script setup>
import { useMoney } from '@/Composables/useMoney';

defineProps({
    storeBreakdown: {
        type: Array,
        default: () => []
    }
});

const { formatMoney } = useMoney();
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <h3 class="text-sm font-black text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-3">{{ $t('reports.stores_comparison_title') }}</h3>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                        <th class="pb-3">{{ $t('inventory.store') }}</th>
                        <th class="pb-3">{{ $t('reports.invoices_count') }}</th>
                        <th class="pb-3">{{ $t('reports.total_issued_sales') }}</th>
                        <th class="pb-3">{{ $t('contacts.paid_amount') }}</th>
                        <th class="pb-3">{{ $t('contacts.remaining_amount') }}</th>
                        <th class="pb-3">{{ $t('reports.gross_profit_trade') }}</th>
                        <th class="pb-3">{{ $t('reports.profit_margin') }}</th>
                        <th class="pb-3 text-left">{{ $t('reports.revenue_share') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                    <tr v-for="st in storeBreakdown" :key="st.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="py-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ st.name }}</td>
                        <td class="py-3 font-mono text-slate-600 dark:text-slate-300">{{ st.invoice_count }}</td>
                        <td class="py-3 font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(st.total_sales) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-emerald-600 dark:text-emerald-400">{{ formatMoney(st.total_paid) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-rose-600 dark:text-rose-400">{{ formatMoney(st.total_remaining) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono font-bold text-theme-primary">{{ formatMoney(st.gross_profit) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-slate-600 dark:text-slate-300">{{ st.margin }}%</td>
                        <td class="py-3 font-mono text-left font-black text-theme-primary">{{ st.share_pct }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="st in storeBreakdown"
                :key="st.id"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <div>
                        <div class="font-black text-xs text-slate-900 dark:text-white">{{ st.name }}</div>
                        <div class="text-[10px] text-slate-400 font-mono">{{ st.invoice_count }} {{ $t('invoices.title') }}</div>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg tab-theme-active font-mono font-black text-xs">
                        {{ st.share_pct }}%
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.total_issued_sales') }}</span>
                        <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(st.total_sales) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.gross_profit_trade') }}</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(st.gross_profit) }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs font-mono pt-1.5 border-t border-slate-200 dark:border-slate-800">
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $t('contacts.paid_amount') }}: {{ formatMoney(st.total_paid) }}</span>
                    <span class="text-rose-600 dark:text-rose-400 font-bold">{{ $t('contacts.remaining_amount') }}: {{ formatMoney(st.total_remaining) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
