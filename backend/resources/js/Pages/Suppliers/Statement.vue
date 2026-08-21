<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    supplier: { type: Object, required: true },
    ledger: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const statementColumns = computed(() => [
    { key: 'date', label: trans('common.date'), mono: true },
    { key: 'type', label: trans('contacts.transaction_type') },
    { key: 'ref_number', label: trans('contacts.reference_no'), mono: true },
    { key: 'credit', label: trans('contacts.period_credit'), mono: true },
    { key: 'debit', label: trans('contacts.period_debit'), mono: true },
    { key: 'balance_after', label: trans('contacts.closing_balance'), mono: true },
    { key: 'notes', label: trans('common.notes') },
]);

const { formatMoney } = useMoney();

const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const activePreset = ref(props.filters.from ? 'custom' : 'all');

const applyFilters = () => {
    router.get(`/suppliers/${props.supplier.id}/statement`, {
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const setQuickDate = (range) => {
    activePreset.value = range;
    const today = new Date();
    if (range === 'today') {
        const str = today.toISOString().split('T')[0];
        dateFrom.value = str;
        dateTo.value = str;
    } else if (range === 'this_month') {
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        dateFrom.value = start.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    } else if (range === 'this_year') {
        const start = new Date(today.getFullYear(), 0, 1);
        dateFrom.value = start.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
    } else if (range === 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilters();
};

const printStatement = () => {
    window.print();
};

// Quick Payment Modal
const showPaymentModal = ref(false);
const paymentForm = useForm({
    amount: props.supplier.current_balance > 0 ? props.supplier.current_balance : '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const savePayment = () => {
    paymentForm.post(`/suppliers/${props.supplier.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentModal.value = false;
        }
    });
};
</script>

<template>
    <Head :title="`${$t('contacts.ledger_title')}: ${supplier.name}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header (Hidden on print) -->
            <div class="print:hidden flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Link
                            href="/suppliers"
                            class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-transparent shrink-0"
                            :title="$t('common.back') || 'رجوع'"
                        >
                            →
                        </Link>
                        <div>
                            <h1 class="text-base sm:text-2xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5">
                                <span>{{ $t('contacts.ledger_title') }}:</span>
                                <span class="text-theme-primary">{{ supplier.name }}</span>
                                <span v-if="supplier.company_name" class="text-xs text-slate-500 dark:text-slate-400 font-normal">({{ supplier.company_name }})</span>
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mt-0.5">
                                {{ $t('common.phone') }}: {{ supplier.phone || '—' }} | {{ $t('common.address') }}: {{ supplier.address || '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <button
                        @click="showPaymentModal = true"
                        type="button"
                        class="flex-1 sm:flex-none h-11 px-5 rounded-2xl btn-primary-theme font-black text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-primary"
                    >
                        <span>💸</span>
                        <span>{{ $t('contacts.record_disbursement_voucher') }}</span>
                    </button>

                    <button
                        @click="printStatement"
                        type="button"
                        class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-2 transition cursor-pointer shadow-xs active:scale-95"
                    >
                        <span>🖨️</span>
                        <span>{{ $t('contacts.print_statement') }}</span>
                    </button>
                </div>
            </div>

            <!-- Filter Controls (Hidden on print) -->
            <div class="print:hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('contacts.report_period') }}:</span>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold">
                            <button
                                @click="setQuickDate('today')"
                                type="button"
                                class="h-9 px-3.5 rounded-xl transition active:scale-95 cursor-pointer shadow-xs"
                                :class="activePreset === 'today' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.today') || 'اليوم' }}
                            </button>
                            <button
                                @click="setQuickDate('this_month')"
                                type="button"
                                class="h-9 px-3.5 rounded-xl transition active:scale-95 cursor-pointer shadow-xs"
                                :class="activePreset === 'this_month' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.this_month') || 'هذا الشهر' }}
                            </button>
                            <button
                                @click="setQuickDate('this_year')"
                                type="button"
                                class="h-9 px-3.5 rounded-xl transition active:scale-95 cursor-pointer shadow-xs"
                                :class="activePreset === 'this_year' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.this_year') || 'هذا العام' }}
                            </button>
                            <button
                                @click="setQuickDate('all')"
                                type="button"
                                class="h-9 px-3.5 rounded-xl transition active:scale-95 cursor-pointer shadow-xs"
                                :class="activePreset === 'all' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.all') || 'الكل' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-2.5 sm:gap-3">
                        <div class="w-full sm:w-44">
                            <DatePicker v-model="dateFrom" :placeholder="$t('contacts.from_date') || 'من تاريخ'" />
                        </div>
                        <div class="w-full sm:w-44">
                            <DatePicker v-model="dateTo" :placeholder="$t('contacts.to_date') || 'إلى تاريخ'" />
                        </div>
                        <button
                            @click="activePreset = 'custom'; applyFilters();"
                            type="button"
                            class="w-full sm:w-auto h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition active:scale-95 cursor-pointer shadow-theme-primary shrink-0"
                        >
                            {{ $t('common.filter') || 'تصفية' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Cards (Bento Grid on Mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1.5">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.period_credit') }}</span>
                    <div class="text-lg sm:text-2xl font-black font-mono text-slate-900 dark:text-white">
                        {{ formatMoney(summary.total_purchases) }} <span class="text-[11px] text-theme-primary">{{ $t('common.currency') }}</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1.5">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.period_debit') }}</span>
                    <div class="text-lg sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(summary.total_payments) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                    </div>
                </div>

                <div class="col-span-2 sm:col-span-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1.5">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.closing_balance') }}</span>
                    <div class="text-lg sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400">
                        {{ formatMoney(summary.current_balance) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                    </div>
                </div>
            </div>

            <!-- Ledger Statement Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="statementColumns"
                    :rows="ledger"
                    :empty-title="$t('contacts.statement_empty')"
                    empty-icon="📜"
                >
                    <!-- Date -->
                    <template #cell-date="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">{{ row.date }}</span>
                    </template>

                    <!-- Type -->
                    <template #cell-type="{ row }">
                        <div class="font-tajawal font-bold text-slate-900 dark:text-white flex items-center gap-1">
                            <span v-if="row.type && row.type.includes('صرف')" class="text-emerald-500">💸</span>
                            <span v-else class="text-theme-primary">📦</span>
                            <span>{{ row.type }}</span>
                        </div>
                    </template>

                    <!-- Ref Number -->
                    <template #cell-ref_number="{ row }">
                        <span class="font-mono text-slate-700 dark:text-slate-300 font-bold">
                            {{ row.ref_number || '—' }}
                        </span>
                    </template>

                    <!-- Credit -->
                    <template #cell-credit="{ row }">
                        <span class="font-mono font-bold text-slate-900 dark:text-white">
                            {{ row.credit > 0 ? formatMoney(row.credit) + ' ' + $t('common.currency') : '—' }}
                        </span>
                    </template>

                    <!-- Debit -->
                    <template #cell-debit="{ row }">
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ row.debit > 0 ? formatMoney(row.debit) + ' ' + $t('common.currency') : '—' }}
                        </span>
                    </template>

                    <!-- Balance After -->
                    <template #cell-balance_after="{ row }">
                        <span class="font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
                            {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Notes -->
                    <template #cell-notes="{ row }">
                        <span class="font-tajawal text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.notes || '—' }}
                        </span>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span v-if="row.type && row.type.includes('صرف')">💸</span>
                                    <span v-else>📦</span>
                                    <span class="font-black text-xs text-slate-900 dark:text-white">{{ row.type }}</span>
                                    <span v-if="row.ref_number" class="font-mono text-xs text-theme-primary font-bold">#{{ row.ref_number }}</span>
                                </div>
                                <span class="font-mono text-[11px] text-slate-400">{{ row.date }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs font-mono py-1">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_credit') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ row.credit > 0 ? formatMoney(row.credit) : '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_debit') }}</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ row.debit > 0 ? formatMoney(row.debit) : '—' }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.closing_balance') }}</span>
                                    <span class="font-black text-rose-600 dark:text-rose-400">{{ formatMoney(row.balance_after) }}</span>
                                </div>
                            </div>

                            <div v-if="row.notes" class="text-[11px] text-slate-500 dark:text-slate-400 font-tajawal border-t border-slate-200 dark:border-slate-800/80 pt-1.5">
                                📝 {{ row.notes }}
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Quick Payment Modal (Smooth Native Pop) -->
        <Teleport to="body">
            <Transition name="modal-zoom">
                <div
                    v-if="showPaymentModal"
                    @click="showPaymentModal = false"
                    class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
                    dir="rtl"
                >
                    <div @click.stop class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 text-slate-900 dark:text-white max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 class="font-black text-base text-slate-900 dark:text-white">{{ $t('contacts.record_disbursement_voucher') }}</h3>
                            <button
                                @click="showPaymentModal = false"
                                type="button"
                                class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs"
                            >
                                ✕
                            </button>
                        </div>

                        <form @submit.prevent="savePayment" class="space-y-4">
                            <div class="p-3.5 bg-slate-50 dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.current_balance') }}:</span>
                                <span class="font-mono font-black text-rose-600 dark:text-rose-400 text-base">{{ formatMoney(supplier.current_balance) }} {{ $t('common.currency') }}</span>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('contacts.voucher_amount') }} ({{ $t('common.currency') }}) *</label>
                                <input
                                    v-model.number="paymentForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-mono font-black text-emerald-600 dark:text-emerald-400 focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('contacts.payment_method') }} *</label>
                                    <select
                                        v-model="paymentForm.payment_method"
                                        class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner font-bold"
                                    >
                                        <option value="cash">{{ $t('pos.cash') }} 💵</option>
                                        <option value="instapay">InstaPay ⚡</option>
                                        <option value="wallet">{{ $t('pos.wallet') }} 📱</option>
                                        <option value="bank">{{ $t('pos.bank') }} 🏦</option>
                                    </select>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('common.date') }} *</label>
                                    <DatePicker v-model="paymentForm.payment_date" :placeholder="$t('common.date')" />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('common.notes') }}</label>
                                <input
                                    v-model="paymentForm.notes"
                                    type="text"
                                    :placeholder="$t('common.notes')"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    @click="showPaymentModal = false"
                                    type="button"
                                    class="h-11 px-5 rounded-2xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="paymentForm.processing"
                                    class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                                >
                                    {{ paymentForm.processing ? '...' : $t('contacts.record_disbursement_voucher') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>