<script setup>
import { useMoney } from '@/Composables/useMoney';

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
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.top_customers_sales') }}</h3>
            <div class="text-xs text-rose-600 dark:text-rose-400 font-bold">
                {{ $t('reports.all_customers_debt') }}: <span class="font-mono font-black">{{ formatMoney(totalCustomersDebt) }} {{ $t('common.currency') }}</span>
            </div>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                        <th class="pb-3">{{ $t('invoices.customer') }}</th>
                        <th class="pb-3">{{ $t('contacts.phone') }}</th>
                        <th class="pb-3">{{ $t('reports.invoices_count') }}</th>
                        <th class="pb-3">{{ $t('reports.total_bought') }}</th>
                        <th class="pb-3">{{ $t('reports.paid_in_period') }}</th>
                        <th class="pb-3">{{ $t('reports.remaining_in_period') }}</th>
                        <th class="pb-3 text-left">{{ $t('reports.cumulative_balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                    <tr v-for="c in customerSales" :key="c.customer_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="py-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ c.name }}</td>
                        <td class="py-3 font-mono text-slate-500 dark:text-slate-400">{{ c.phone || '-' }}</td>
                        <td class="py-3 font-mono text-slate-600 dark:text-slate-300">{{ c.total_invoices }}</td>
                        <td class="py-3 font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(c.total_bought) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-emerald-600 dark:text-emerald-400">{{ formatMoney(c.total_paid) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-rose-600 dark:text-rose-400">{{ formatMoney(c.total_debt_in_period) }} {{ $t('common.currency') }}</td>
                        <td class="py-3 font-mono text-left font-black" :class="c.current_balance > 0 ? 'text-theme-primary' : 'text-slate-500 dark:text-slate-400'">
                            {{ formatMoney(c.current_balance) }} {{ $t('common.currency') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="c in customerSales"
                :key="c.customer_id"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <div>
                        <div class="font-black text-xs text-slate-900 dark:text-white">{{ c.name }}</div>
                        <div class="text-[10px] text-slate-400 font-mono">{{ c.phone || '-' }}</div>
                    </div>
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.cumulative_balance') }}</span>
                        <span class="font-mono font-black text-xs" :class="c.current_balance > 0 ? 'text-theme-primary' : 'text-slate-500 dark:text-slate-400'">
                            {{ formatMoney(c.current_balance) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.total_bought') }}</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ formatMoney(c.total_bought) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.paid_in_period') }}</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(c.total_paid) }}</span>
                    </div>
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('reports.remaining_in_period') }}</span>
                        <span class="font-black text-rose-600 dark:text-rose-400">{{ formatMoney(c.total_debt_in_period) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
