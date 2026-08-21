<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppModal from '@/Components/Common/AppModal.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';
import { notifySuccess } from '@/helpers/alert';
import {
    Printer,
    FileText,
    Pencil,
    Ban,
    Copy,
    ArrowRight,
    AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
    invoice: { type: Object, required: true },
});

const { formatMoney } = useMoney();

const showCancelModal = ref(false);
const cancelReason = ref('');
const isCancelling = ref(false);

const printThermal = () => {
    window.open(`/invoices/${props.invoice.id}/print/thermal`, '_blank', 'width=400,height=600');
};

const printA4 = () => {
    window.open(`/invoices/${props.invoice.id}/print/a4`, '_blank', 'width=900,height=800');
};

const copyInvoiceNumber = async () => {
    try {
        await navigator.clipboard.writeText(props.invoice.invoice_number);
        notifySuccess('تم نسخ رقم الفاتورة إلى الحافظة');
    } catch (e) {}
};

const confirmCancel = () => {
    if (!cancelReason.value || cancelReason.value.trim().length < 3) {
        alert(trans('invoices.cancel_reason_label') || 'يرجى كتابة سبب الإلغاء (3 أحرف على الأقل)');
        return;
    }
    isCancelling.value = true;
    router.post(`/invoices/${props.invoice.id}/cancel`, {
        reason: cancelReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
        onFinish: () => {
            isCancelling.value = false;
        },
    });
};

const getPaymentBadge = computed(() => {
    const inv = props.invoice;
    if (inv.payment_type === 'cash') {
        if (inv.payment_method === 'instapay') return { label: 'إستاباي ⚡', class: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30' };
        if (inv.payment_method === 'wallet' || inv.payment_method === 'e_wallet') return { label: 'محفظة 📱', class: 'bg-teal-500/15 text-teal-400 border-teal-500/30' };
        return { label: trans('invoices.payment_cash') || 'كاش 💵', class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' };
    }
    if (inv.payment_type === 'credit') return { label: trans('invoices.payment_credit') || 'آجل', class: 'bg-rose-500/15 text-rose-400 border-rose-500/30' };
    if (inv.payment_type === 'partial') return { label: trans('invoices.payment_partial') || 'جزئي', class: 'bg-amber-500/15 text-amber-400 border-amber-500/30' };
    return { label: inv.payment_type, class: 'bg-slate-800 text-slate-400' };
});

const showActions = computed(() => [
    {
        label: trans('invoices.print_thermal') || 'طباعة إيصال حراري (80mm)',
        icon: Printer,
        variant: 'success',
        onClick: printThermal,
        show: true
    },
    {
        label: trans('invoices.print_a4') || 'طباعة فاتورة ضريبية (A4)',
        icon: FileText,
        onClick: printA4,
        show: true
    },
    {
        label: trans('invoices.edit_invoice') || 'تعديل بيانات الفاتورة',
        icon: Pencil,
        variant: 'warning',
        href: `/invoices/${props.invoice.id}/edit`,
        show: props.invoice.status !== 'cancelled'
    },
    {
        label: 'نسخ رقم الفاتورة',
        icon: Copy,
        onClick: copyInvoiceNumber,
        show: true
    },
    {
        label: trans('invoices.cancel_invoice') || 'إلغاء الفاتورة',
        icon: Ban,
        variant: 'danger',
        onClick: () => { showCancelModal.value = true; },
        show: props.invoice.status !== 'cancelled'
    }
]);
</script>

<template>
    <Head :title="`${$t('invoices.invoice_number')} #${invoice.invoice_number}`" />

    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
            <!-- Header & Action Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <!-- Title & Badges -->
                    <div class="flex items-center gap-2.5 min-w-0">
                        <Link
                            href="/invoices"
                            class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-slate-700 shrink-0"
                            :title="$t('common.back') || 'الرجوع'"
                        >
                            <ArrowRight class="w-4 h-4" />
                        </Link>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white font-tajawal leading-tight">
                                    {{ $t('invoices.invoice_number') }} <span class="font-mono text-theme-primary">#{{ invoice.invoice_number }}</span>
                                </h1>
                                <span class="px-2.5 py-0.5 rounded-xl text-[10.5px] font-bold border shrink-0" :class="getPaymentBadge.class">
                                    {{ getPaymentBadge.label }}
                                </span>
                                <span v-if="invoice.status === 'cancelled'" class="px-2.5 py-0.5 rounded-xl text-[10.5px] font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30 shrink-0">
                                    {{ $t('invoices.status_cancelled') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <!-- Quick Thermal Print (Primary CTA) -->
                        <button
                            @click="printThermal"
                            type="button"
                            class="flex-1 sm:flex-none h-11 px-4 sm:px-5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md shadow-emerald-500/20 transition active:scale-95 cursor-pointer"
                        >
                            <Printer class="w-4 h-4" />
                            <span>{{ $t('invoices.print_thermal') || 'طباعة حراري' }}</span>
                        </button>

                        <!-- Quick Edit (If Active) -->
                        <Link
                            v-if="invoice.status !== 'cancelled'"
                            :href="`/invoices/${invoice.id}/edit`"
                            class="h-11 px-3.5 rounded-2xl btn-primary-theme font-black text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-theme-primary"
                            :title="$t('invoices.edit_invoice')"
                        >
                            <Pencil class="w-4 h-4" />
                            <span class="hidden md:inline">{{ $t('invoices.edit_invoice') }}</span>
                        </Link>

                        <!-- Action Menu (All Other Actions) -->
                        <ActionMenu
                            :items="showActions"
                            :title="`#${invoice.invoice_number}`"
                            buttonClass="h-11 px-3 rounded-2xl"
                        />
                    </div>
                </div>

                <!-- Meta Strip -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-4 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-500 dark:text-slate-400 font-bold">
                    <span class="inline-flex items-center gap-1">
                        <span>📅 {{ $t('common.date') }}:</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300">{{ invoice.formatted_created_at || invoice.invoice_date }}</span>
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1">
                        <span>🏬 {{ $t('common.store') }}:</span>
                        <span class="text-slate-700 dark:text-slate-300 font-black">{{ invoice.store?.name }}</span>
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1">
                        <span>👤 {{ $t('invoices.cashier') }}:</span>
                        <span class="text-slate-700 dark:text-slate-300 font-black">{{ invoice.cashier_name }}</span>
                    </span>
                </div>
            </div>

            <!-- Customer & Invoice Overview Card (Bento Grid on Mobile) -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                <!-- Customer Card -->
                <div class="col-span-2 md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 space-y-2 shadow-xs">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.customer_details') }}</span>
                    <div class="text-base font-black text-slate-900 dark:text-white">
                        {{ invoice.customer?.name || $t('pos.cash_customer') }}
                    </div>
                    <div v-if="invoice.customer?.phone" class="text-xs text-slate-500 dark:text-slate-400 font-mono" dir="ltr">
                        📱 {{ invoice.customer.phone }}
                    </div>
                    <div v-if="invoice.customer" class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('contacts.balance') }}: <span class="font-mono font-bold text-theme-primary">{{ formatMoney(invoice.customer.balance) }} {{ $t('common.currency') }}</span>
                    </div>
                </div>

                <!-- Financial Card 1 -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 space-y-1.5 shadow-xs">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.subtotal') }} & {{ $t('invoices.discount') }}</span>
                    <div class="text-lg sm:text-2xl font-black font-mono text-slate-900 dark:text-white">
                        {{ formatMoney(invoice.total_amount) }} <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $t('common.currency') }}</span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('invoices.discount') }}: <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ formatMoney(invoice.discount_amount) }} {{ $t('common.currency') }}</span>
                    </div>
                </div>

                <!-- Financial Card 2 (Net & Paid) -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 space-y-1.5 shadow-xs">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('invoices.net_total') }} & {{ $t('invoices.paid') }}</span>
                    <div class="text-lg sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(invoice.net_total) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                    </div>
                    <div class="text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between">
                        <span>{{ $t('invoices.paid') }}: <b class="font-mono text-slate-900 dark:text-white">{{ formatMoney(invoice.paid_amount) }}</b></span>
                        <span v-if="Number(invoice.remaining_amount) > 0" class="text-rose-600 dark:text-rose-400 font-bold">
                            {{ $t('invoices.remaining') }}: <b class="font-mono">{{ formatMoney(invoice.remaining_amount) }}</b>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Items Table & Mobile Cards -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4 font-tajawal">
                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                    <span>📦</span>
                    <span>{{ $t('pos.cart_items') }}</span>
                </h3>

                <!-- Desktop Table (Hidden on Mobile) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                <th class="pb-3">#</th>
                                <th class="pb-3">{{ $t('inventory.item_name') }}</th>
                                <th class="pb-3">{{ $t('common.quantity') }}</th>
                                <th class="pb-3">{{ $t('invoices.unit_price') }}</th>
                                <th class="pb-3 text-left">{{ $t('common.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                            <tr v-for="(item, idx) in invoice.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-3 text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-3 font-bold text-slate-900 dark:text-white font-tajawal">
                                    {{ item.item_name }}
                                    <span v-if="item.item_code" class="text-[10px] text-slate-400 font-mono block">{{ $t('inventory.item_code') }}: {{ item.item_code }}</span>
                                </td>
                                <td class="py-3 font-mono font-bold text-slate-700 dark:text-slate-200">
                                    {{ item.quantity }} {{ item.unit }}
                                </td>
                                <td class="py-3 font-mono text-slate-600 dark:text-slate-300">
                                    {{ formatMoney(item.unit_price) }}
                                </td>
                                <td class="py-3 font-mono font-bold text-emerald-600 dark:text-emerald-400 text-left text-sm">
                                    {{ formatMoney(item.total_price) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards View (Visible on Small Screens) -->
                <div class="md:hidden space-y-2.5">
                    <div
                        v-for="(item, idx) in invoice.items"
                        :key="item.id"
                        class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-bold text-xs text-slate-900 dark:text-white font-tajawal">
                                <span>{{ idx + 1 }}. </span>{{ item.item_name }}
                                <span v-if="item.item_code" class="text-[10px] text-slate-400 font-mono block">#{{ item.item_code }}</span>
                            </div>
                            <span class="font-mono font-black text-xs text-emerald-600 dark:text-emerald-400 shrink-0">
                                {{ formatMoney(item.total_price) }} {{ $t('common.currency') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-mono pt-1 border-t border-slate-200 dark:border-slate-800/80">
                            <span>الكمية: <b class="text-slate-900 dark:text-white">{{ item.quantity }} {{ item.unit }}</b></span>
                            <span>السعر: <b class="text-slate-900 dark:text-white">{{ formatMoney(item.unit_price) }}</b></span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="invoice.notes" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
                    <span class="font-bold text-theme-primary">{{ $t('common.notes') }}:</span> {{ invoice.notes }}
                </div>
            </div>

            <!-- Additional Expenses Section (if any) -->
            <div v-if="invoice.expenses && invoice.expenses.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-3 font-tajawal">
                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                    <span>🚚</span>
                    <span>{{ $t('invoices.shipping') }}</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                <th class="pb-2">{{ $t('common.notes') }}</th>
                                <th class="pb-2 text-left font-mono">{{ $t('common.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                            <tr v-for="exp in invoice.expenses" :key="exp.id">
                                <td class="py-2.5 font-bold text-slate-900 dark:text-slate-200 font-tajawal">{{ exp.title }}</td>
                                <td class="py-2.5 font-mono font-bold text-emerald-600 dark:text-emerald-400 text-left">{{ formatMoney(exp.amount) }} {{ $t('common.currency') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments History Log Section -->
            <div v-if="invoice.payments && invoice.payments.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-3 font-tajawal">
                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                    <span>💳</span>
                    <span>{{ $t('invoices.financial_summary') }}</span>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                <th class="pb-2">{{ $t('common.date') }}</th>
                                <th class="pb-2">{{ $t('invoices.paid') }}</th>
                                <th class="pb-2">{{ $t('invoices.payment_method') }}</th>
                                <th class="pb-2 text-left">{{ $t('common.user') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                            <tr v-for="pay in invoice.payments" :key="pay.id">
                                <td class="py-2.5 font-mono text-slate-700 dark:text-slate-300">{{ pay.payment_date }}</td>
                                <td class="py-2.5 font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatMoney(pay.amount) }} {{ $t('common.currency') }}</td>
                                <td class="py-2.5 font-tajawal text-slate-700 dark:text-slate-300">
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[11px] font-bold">
                                        {{ pay.payment_method === 'instapay' ? '⚡ ' + $t('invoices.payment_instapay') : (pay.payment_method === 'wallet' ? '📱 ' + $t('invoices.payment_wallet') : '💵 ' + $t('invoices.payment_cash')) }}
                                    </span>
                                </td>
                                <td class="py-2.5 text-left text-slate-500 dark:text-slate-400 font-tajawal">{{ pay.user_name || $t('invoices.cashier') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cancel Invoice Reason Modal -->
        <AppModal
            :show="showCancelModal"
            :title="$t('invoices.cancel_modal_title')"
            :icon="AlertTriangle"
            max-width="md"
            @close="showCancelModal = false"
        >
            <div class="space-y-4">
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{ $t('invoices.cancel_modal_desc', { number: invoice.invoice_number }) }}
                </p>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.cancel_reason_label') }}</label>
                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        :placeholder="$t('invoices.cancel_reason_placeholder')"
                        class="w-full p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-rose-500 focus:outline-none shadow-inner"
                    ></textarea>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center gap-2 w-full">
                    <button
                        type="button"
                        class="flex-1 h-11 rounded-2xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showCancelModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="isCancelling || !cancelReason || cancelReason.trim().length < 3"
                        class="flex-1 h-11 rounded-2xl bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white text-xs font-black transition active:scale-95 shadow-md shadow-rose-600/30 cursor-pointer"
                        @click="confirmCancel"
                    >
                        {{ isCancelling ? '...' : $t('invoices.confirm_cancel_btn') }}
                    </button>
                </div>
            </template>
        </AppModal>
    </AppLayout>
</template>

