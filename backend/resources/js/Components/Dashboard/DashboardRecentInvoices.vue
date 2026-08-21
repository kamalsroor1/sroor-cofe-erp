<script setup>
import { Link } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

defineProps({
    recentInvoices: {
        type: Array,
        default: () => []
    }
});

const { formatMoney } = useMoney();

const getPaymentTypeBadge = (type) => {
    switch (type) {
        case 'cash': return { label: trans('invoices.cash') || 'نقدي', class: 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' };
        case 'credit': return { label: trans('invoices.credit') || 'آجل', class: 'bg-rose-500/15 text-rose-400 border border-rose-500/30' };
        case 'partial': return { label: trans('invoices.partial') || 'جزئي', class: 'bg-amber-500/15 text-amber-400 border border-amber-500/30' };
        default: return { label: type, class: 'bg-slate-800 text-slate-400' };
    }
};
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 sm:space-y-5 shadow-xs font-tajawal">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <Receipt class="w-5 h-5 text-theme-primary" />
                <h2 class="text-base lg:text-lg font-black text-slate-900 dark:text-white">{{ $t('dashboard.recent_invoices_title') }}</h2>
            </div>
            <Link href="/invoices" class="text-xs font-black text-theme-primary hover:underline transition active:scale-95">
                {{ $t('dashboard.view_all') }}
            </Link>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-xs">
                        <th class="pb-3">{{ $t('dashboard.invoice_number_col') }}</th>
                        <th class="pb-3">{{ $t('dashboard.customer_col') }}</th>
                        <th class="pb-3">{{ $t('dashboard.payment_method_col') }}</th>
                        <th class="pb-3">{{ $t('dashboard.total_col') }}</th>
                        <th class="pb-3">{{ $t('dashboard.paid_col') }}</th>
                        <th class="pb-3 text-left">{{ $t('dashboard.time_col') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans text-xs">
                    <tr v-for="inv in recentInvoices" :key="inv.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="py-3.5 font-mono font-black text-theme-primary">
                            <Link :href="`/invoices/${inv.id}`" class="hover:underline">
                                #{{ inv.invoice_number }}
                            </Link>
                        </td>
                        <td class="py-3.5 font-bold text-slate-800 dark:text-slate-200 font-tajawal">{{ inv.customer_name }}</td>
                        <td class="py-3.5 font-tajawal">
                            <span class="px-2.5 py-1 rounded-xl text-xs font-black" :class="getPaymentTypeBadge(inv.payment_type).class">
                                {{ getPaymentTypeBadge(inv.payment_type).label }}
                            </span>
                        </td>
                        <td class="py-3.5 font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}</td>
                        <td class="py-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}</td>
                        <td class="py-3.5 font-mono text-slate-500 dark:text-slate-400 text-left text-xs">{{ inv.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="inv in recentInvoices"
                :key="inv.id"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs"
            >
                <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <Link :href="`/invoices/${inv.id}`" class="font-mono font-black text-sm text-theme-primary hover:underline">
                        #{{ inv.invoice_number }}
                    </Link>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="getPaymentTypeBadge(inv.payment_type).class">
                        {{ getPaymentTypeBadge(inv.payment_type).label }}
                    </span>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-200 font-tajawal">{{ inv.customer_name }}</span>
                    <span class="font-mono text-slate-400 text-[10px]">{{ inv.created_at }}</span>
                </div>

                <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200 dark:border-slate-800">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('dashboard.total_col') }}</span>
                        <span class="font-mono font-black text-slate-900 dark:text-white">{{ formatMoney(inv.net_total) }} {{ $t('common.currency') }}</span>
                    </div>
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('dashboard.paid_col') }}</span>
                        <span class="font-mono font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(inv.paid_amount) }} {{ $t('common.currency') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="recentInvoices.length === 0" class="py-12 text-center text-slate-400 font-bold text-xs">
            {{ $t('dashboard.no_invoices_today') }}
        </div>
    </div>
</template>
