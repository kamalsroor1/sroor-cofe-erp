<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
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
        case 'cash': return { label: trans('invoices.cash') || 'نقدي', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' };
        case 'credit': return { label: trans('invoices.credit') || 'آجل', class: 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' };
        case 'partial': return { label: trans('invoices.partial') || 'جزئي', class: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' };
        default: return { label: type, class: 'bg-slate-800 text-slate-400' };
    }
};

const invoiceColumns = computed(() => [
    { key: 'invoice_number', label: trans('dashboard.invoice_number_col') || 'رقم الفاتورة', mono: true },
    { key: 'customer_name', label: trans('dashboard.customer_col') || 'العميل' },
    { key: 'payment_type', label: trans('dashboard.payment_method_col') || 'طريقة الدفع' },
    { key: 'net_total', label: trans('dashboard.total_col') || 'الإجمالي', mono: true },
    { key: 'paid_amount', label: trans('dashboard.paid_col') || 'المدفوع', mono: true },
    { key: 'created_at', label: trans('dashboard.time_col') || 'الوقت', mono: true, align: 'left' },
]);
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

        <DataTable
            :columns="invoiceColumns"
            :rows="recentInvoices"
            :empty-title="$t('dashboard.no_recent_invoices')"
            empty-icon="🧾"
        >
            <!-- Number -->
            <template #cell-invoice_number="{ row }">
                <Link :href="`/invoices/${row.id}`" class="font-mono font-black text-theme-primary hover:underline">
                    #{{ row.invoice_number }}
                </Link>
            </template>

            <!-- Customer -->
            <template #cell-customer_name="{ row }">
                <span class="font-bold text-slate-800 dark:text-slate-200 font-tajawal">{{ row.customer_name }}</span>
            </template>

            <!-- Payment Type -->
            <template #cell-payment_type="{ row }">
                <span class="px-2.5 py-1 rounded-xl text-xs font-black" :class="getPaymentTypeBadge(row.payment_type).class">
                    {{ getPaymentTypeBadge(row.payment_type).label }}
                </span>
            </template>

            <!-- Net Total -->
            <template #cell-net_total="{ row }">
                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(row.net_total) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Paid -->
            <template #cell-paid_amount="{ row }">
                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.paid_amount) }} {{ $t('common.currency') }}</span>
            </template>

            <!-- Created At -->
            <template #cell-created_at="{ row }">
                <span class="font-mono text-slate-500 dark:text-slate-400 text-left text-xs">{{ row.created_at }}</span>
            </template>

            <!-- Mobile Card Custom Slot -->
            <template #mobile-card="{ row }">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                    <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                        <Link :href="`/invoices/${row.id}`" class="font-mono font-black text-sm text-theme-primary hover:underline">
                            #{{ row.invoice_number }}
                        </Link>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="getPaymentTypeBadge(row.payment_type).class">
                            {{ getPaymentTypeBadge(row.payment_type).label }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-xs font-tajawal">
                        <span class="font-bold text-slate-900 dark:text-white">{{ row.customer_name }}</span>
                        <span class="text-slate-400 font-mono text-[11px]">{{ row.created_at }}</span>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-800 pt-2 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('invoices.paid') }}</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(row.paid_amount) }}</span>
                        </div>
                        <div class="text-left">
                            <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('invoices.grand_total') }}</span>
                            <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(row.net_total) }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>
