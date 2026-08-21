<script setup>
import { useMoney } from '@/Composables/useMoney';

defineProps({
    itemProfits: {
        type: Array,
        default: () => []
    }
});

const { formatMoney } = useMoney();
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.items_profit_detail') }}</h3>
            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $t('reports.items_sold_count', { count: itemProfits.length }) }}</span>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                        <th class="pb-3">{{ $t('inventory.item_name') }}</th>
                        <th class="pb-3">{{ $t('expenses.category') }}</th>
                        <th class="pb-3">{{ $t('invoices.quantity') }}</th>
                        <th class="pb-3">{{ $t('reports.sales_summary') }}</th>
                        <th class="pb-3">{{ $t('reports.cogs') }}</th>
                        <th class="pb-3">{{ $t('reports.gross_profit_trade') }}</th>
                        <th class="pb-3 text-left">{{ $t('reports.profit_margin') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                    <tr v-for="item in itemProfits" :key="item.item_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="py-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ item.name }}</td>
                        <td class="py-3 text-slate-500 dark:text-slate-400 font-tajawal">{{ item.category || $t('common.all') }}</td>
                        <td class="py-3 font-mono text-theme-primary font-bold">{{ item.total_qty }} {{ item.unit }}</td>
                        <td class="py-3 font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(item.total_revenue) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-slate-500 dark:text-slate-400">{{ formatMoney(item.total_cogs) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(item.profit) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-left font-bold text-theme-primary">{{ item.margin }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="item in itemProfits"
                :key="item.item_id"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <div>
                        <div class="font-black text-xs text-slate-900 dark:text-white">{{ item.name }}</div>
                        <div class="text-[10px] text-slate-400 font-tajawal">{{ item.category || $t('common.all') }}</div>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-mono font-black text-xs">
                        {{ item.margin }}%
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('invoices.quantity') }}</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ item.total_qty }} {{ item.unit }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.sales_summary') }}</span>
                        <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(item.total_revenue) }}</span>
                    </div>
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.gross_profit_trade') }}</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(item.profit) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
