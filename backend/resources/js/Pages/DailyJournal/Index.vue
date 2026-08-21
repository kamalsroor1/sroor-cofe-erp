<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import AppModal from '@/Components/Common/AppModal.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    date: { type: String, required: true },
    active_shift: { type: Object, default: null },
    summary: { type: Object, required: true },
    invoices: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
});

const { formatMoney } = useMoney();

const invoiceColumns = computed(() => [
    { key: 'invoice_number', label: trans('invoices.invoice_number'), mono: true },
    { key: 'customer_name', label: trans('invoices.customer') },
    { key: 'net_total', label: trans('common.total'), mono: true },
    { key: 'payment_method', label: trans('pos.payment_method') },
]);

const expenseColumns = computed(() => [
    { key: 'title', label: trans('expenses.expense_item') },
    { key: 'cost_center_label', label: trans('expenses.cost_center') },
    { key: 'amount', label: trans('common.amount'), mono: true },
    { key: 'payment_method', label: trans('pos.payment_method') },
]);

const selectedDate = ref(props.date);

watch(selectedDate, (newDate) => {
    if (newDate && newDate !== props.date) {
        router.get('/daily-journal', { date: newDate }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }
});

// Shift Open Modal
const showOpenShiftModal = ref(false);
const openShiftForm = useForm({
    opening_cash_balance: '0.00',
    notes: '',
});

const submitOpenShift = () => {
    openShiftForm.post('/daily-journal/open-shift', {
        preserveScroll: true,
        onSuccess: () => {
            showOpenShiftModal.value = false;
        }
    });
};

// Shift Close Modal (Z-Report)
const showCloseShiftModal = ref(false);
const closeShiftForm = useForm({
    actual_cash_balance: '',
    notes: '',
});

const submitCloseShift = () => {
    if (!props.active_shift) return;
    closeShiftForm.post(`/daily-journal/close-shift/${props.active_shift.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showCloseShiftModal.value = false;
        }
    });
};

// Quick Expense Modal
const showExpenseModal = ref(false);
const expenseForm = useForm({
    title: '',
    amount: '',
    cost_center: 'operational',
    payment_method: 'cash',
    notes: '',
});

const costCenterOptions = computed(() => [
    { id: 'operational', name: `${trans('expenses.cc_operational') || 'مصاريف تشغيلية ونثريات عامة'} ☕` },
    { id: 'salaries', name: `${trans('expenses.cc_salaries') || 'رواتب وعمالة وإكراميات'} 👥` },
    { id: 'utilities', name: `${trans('expenses.cc_utilities') || 'كهرباء ومياه وغاز ومرافق'} ⚡` },
    { id: 'rent', name: `${trans('expenses.cc_rent') || 'إيجارات مقرات وفروع'} 🏬` },
    { id: 'packaging', name: `${trans('expenses.cc_packaging') || 'مطبوعات وكراتين وتعبئة'} 📦` },
    { id: 'hospitality', name: `${trans('expenses.cc_hospitality') || 'ضيافة ونظافة وبوفيه'} 🧹` },
    { id: 'maintenance', name: `${trans('expenses.cc_maintenance') || 'صيانة معدات وديكورات'} 🔧` },
    { id: 'vehicles', name: `${trans('expenses.cc_vehicles') || 'وقود وزيوت وصيانة سيارات'} 🚚` },
    { id: 'shipping', name: `${trans('expenses.cc_shipping') || 'شحن ونولون وتوصيل خارجي'} ✈️` },
]);

const paymentMethodOptions = computed(() => [
    { id: 'cash', name: `${trans('treasury.cash_drawer') || 'نقدي من درج الخزينة'} 💵` },
    { id: 'instapay', name: `${trans('treasury.instapay') || 'إنستاباي'} ⚡` },
    { id: 'wallet', name: `${trans('treasury.e_wallet') || 'محفظة إلكترونية'} 📱` },
    { id: 'bank', name: `${trans('treasury.bank_transfer') || 'حساب بنكي'} 🏦` },
]);

const submitExpense = () => {
    expenseForm.post('/daily-journal/expense', {
        preserveScroll: true,
        onSuccess: () => {
            expenseForm.reset();
            showExpenseModal.value = false;
        }
    });
};

const printJournal = () => {
    window.open(`/daily-journal/print?date=${selectedDate.value}`, '_blank');
};
</script>

<template>
    <Head :title="`${$t('treasury.daily_journal')} - ${selectedDate}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header Banner -->
            <PageHeader
                :title="$t('treasury.daily_journal')"
                :subtitle="$t('treasury.live_balances')"
                icon="💵"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="w-44">
                            <DatePicker v-model="selectedDate" :placeholder="$t('treasury.select_date_placeholder')" />
                        </div>

                        <button
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-rose-600/90 hover:bg-rose-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-rose-600/20 transition active:scale-95 cursor-pointer"
                            @click="showExpenseModal = true"
                        >
                            <span>💸</span>
                            <span>{{ $t('treasury.record_expense_modal') }}</span>
                        </button>

                        <button
                            v-if="!active_shift"
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition active:scale-95 cursor-pointer"
                            @click="showOpenShiftModal = true"
                        >
                            <span>🟢</span>
                            <span>{{ $t('treasury.open_shift') }}</span>
                        </button>
                        <button
                            v-else
                            type="button"
                            class="h-11 px-4 rounded-2xl btn-primary-theme font-black text-xs flex items-center gap-1.5 transition transform active:scale-95 cursor-pointer"
                            @click="showCloseShiftModal = true"
                        >
                            <span>🔒</span>
                            <span>{{ $t('treasury.close_shift') }}</span>
                        </button>

                        <button
                            type="button"
                            class="h-11 px-3.5 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center gap-1 transition active:scale-95 cursor-pointer border border-slate-200 dark:border-transparent"
                            @click="printJournal"
                        >
                            <span>🖨️</span>
                            <span>{{ $t('reports.print_report') }}</span>
                        </button>
                    </div>
                </template>
            </PageHeader>

            <!-- Active Shift Card -->
            <div
                class="rounded-3xl p-3.5 sm:p-5 border flex flex-col md:flex-row items-start md:items-center justify-between gap-3 sm:gap-4 shadow-xs font-tajawal bg-white dark:bg-slate-900"
                :class="active_shift ? 'border-emerald-500/30' : 'border-slate-200 dark:border-slate-800'"
            >
                <div class="flex items-center gap-3 sm:gap-4">
                    <div
                        class="w-11 sm:w-12 h-11 sm:h-12 rounded-2xl flex items-center justify-center text-xl sm:text-2xl font-black shrink-0"
                        :class="active_shift ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500'"
                    >
                        {{ active_shift ? '🟢' : '🔒' }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-black text-slate-900 dark:text-white text-sm sm:text-base">
                                {{ active_shift ? `${$t('nav.active_shift')}: #${active_shift.shift_number || active_shift.id}` : $t('treasury.shift_closed_now') }}
                            </span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="active_shift ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                            >
                                {{ active_shift ? $t('treasury.shift_active_desc') : $t('treasury.no_active_shift_desc') }}
                            </span>
                        </div>
                        <p v-if="active_shift" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $t('treasury.cashier_label') }}: <strong class="text-slate-900 dark:text-white">{{ active_shift.user_name }}</strong> | {{ $t('treasury.opened_at_label') }}: <span class="font-mono text-theme-primary font-bold">{{ active_shift.opened_at }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="active_shift" class="text-left font-mono">
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('treasury.opening_cash') }}</span>
                    <div class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(active_shift.opening_cash_balance) }} <span class="text-xs text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Matrix Cards (2x2 Bento on mobile) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 font-tajawal">
                <MetricCard
                    :title="$t('treasury.inflow_cash')"
                    :value="formatMoney(summary.total_cash_in)"
                    :currency="$t('common.currency')"
                    :subtitle="`${$t('treasury.cash_sales')}: ${formatMoney(summary.cash_sales)}`"
                    variant="success"
                />

                <MetricCard
                    :title="$t('treasury.outflow_cash')"
                    :value="formatMoney(summary.total_cash_out)"
                    :currency="$t('common.currency')"
                    :subtitle="`${$t('treasury.operating_expenses')}: ${formatMoney(summary.total_expenses)}`"
                    variant="danger"
                />

                <MetricCard
                    :title="$t('treasury.net_cash_today')"
                    :value="formatMoney(summary.net_cash_today)"
                    :currency="$t('common.currency')"
                    :subtitle="`${$t('treasury.recorded_credit_sales')}: ${formatMoney(summary.credit_sales)}`"
                    :variant="summary.net_cash_today >= 0 ? 'primary' : 'danger'"
                />

                <MetricCard
                    :title="$t('treasury.expected_in_drawer_now')"
                    :value="formatMoney(summary.expected_cash_in_drawer)"
                    :currency="$t('common.currency')"
                    :subtitle="`${$t('treasury.including_opening_cash')}: ${formatMoney(summary.opening_cash_balance)}`"
                    variant="primary"
                />
            </div>

            <!-- Two Columns: Invoices of the Day & Expenses of the Day -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 font-tajawal">
                <!-- Invoices Log of Date -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <span>🧾</span>
                            <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ $t('treasury.today_invoices') }} ({{ invoices.length }})</h3>
                        </div>
                    </div>

                    <DataTable
                        :columns="invoiceColumns"
                        :rows="invoices"
                        :empty-title="$t('treasury.empty_today_invoices')"
                        empty-icon="🧾"
                    >
                        <!-- Invoice Number -->
                        <template #cell-invoice_number="{ row }">
                            <Link :href="`/invoices/${row.id}`" class="text-theme-primary font-mono font-bold hover:underline">
                                {{ row.invoice_number }}
                            </Link>
                        </template>

                        <!-- Customer -->
                        <template #cell-customer_name="{ row }">
                            <span class="text-slate-800 dark:text-slate-200 font-tajawal font-bold">{{ row.customer_name }}</span>
                        </template>

                        <!-- Net Total -->
                        <template #cell-net_total="{ row }">
                            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(row.net_total) }} {{ $t('common.currency') }}</span>
                        </template>

                        <!-- Payment Method -->
                        <template #cell-payment_method="{ row }">
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-tajawal">
                                {{ row.payment_method === 'cash' ? $t('pos.payment_cash') : (row.payment_method === 'credit' ? $t('pos.payment_credit') : $t('pos.payment_partial')) }}
                            </span>
                        </template>
                    </DataTable>
                </div>

                <!-- Expenses Log of Date -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <span>💸</span>
                            <h3 class="font-black text-sm text-slate-900 dark:text-white">{{ $t('treasury.today_expenses') }} ({{ expenses.length }})</h3>
                        </div>
                    </div>

                    <DataTable
                        :columns="expenseColumns"
                        :rows="expenses"
                        :empty-title="$t('treasury.empty_today_expenses')"
                        empty-icon="💸"
                    >
                        <!-- Title -->
                        <template #cell-title="{ row }">
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-tajawal">{{ row.title }}</span>
                        </template>

                        <!-- Cost Center -->
                        <template #cell-cost_center_label="{ row }">
                            <span class="text-slate-500 dark:text-slate-400 font-tajawal text-[11px]">{{ row.cost_center_label }}</span>
                        </template>

                        <!-- Amount -->
                        <template #cell-amount="{ row }">
                            <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ formatMoney(row.amount) }} {{ $t('common.currency') }}</span>
                        </template>

                        <!-- Payment Method -->
                        <template #cell-payment_method="{ row }">
                            <span class="text-slate-500 dark:text-slate-400 font-tajawal text-[11px]">{{ row.payment_method }}</span>
                        </template>
                    </DataTable>
                </div>
            </div>

        </div>

        <!-- Open Shift Modal -->
        <AppModal
            :show="showOpenShiftModal"
            :title="$t('treasury.open_shift_modal_title')"
            icon="🟢"
            max-width="md"
            @close="showOpenShiftModal = false"
        >
            <form id="openShiftForm" @submit.prevent="submitOpenShift" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.opening_cash_field') }}</label>
                    <input
                        v-model="openShiftForm.opening_cash_balance"
                        type="number"
                        step="0.01"
                        required
                        placeholder="0.00"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm text-emerald-600 dark:text-emerald-400 font-mono font-black focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.open_notes_field') }}</label>
                    <input
                        v-model="openShiftForm.notes"
                        type="text"
                        :placeholder="$t('invoices.notes')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showOpenShiftModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        form="openShiftForm"
                        :disabled="openShiftForm.processing"
                        class="h-11 px-6 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-lg shadow-emerald-600/20 transition transform active:scale-95 cursor-pointer disabled:opacity-50"
                    >
                        {{ openShiftForm.processing ? $t('common.save') + '...' : $t('treasury.start_shift_btn') }}
                    </button>
                </div>
            </template>
        </AppModal>

        <!-- Close Shift Modal (Z-Report) -->
        <AppModal
            :show="showCloseShiftModal"
            :title="$t('treasury.close_shift_modal_title')"
            :subtitle="active_shift?.shift_number"
            icon="🔒"
            max-width="md"
            @close="showCloseShiftModal = false"
        >
            <div class="bg-slate-50 dark:bg-slate-950/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 space-y-2 mb-4">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 dark:text-slate-400">{{ $t('treasury.expected_cash_calculated') }}:</span>
                    <span class="font-mono font-black text-theme-primary">{{ formatMoney(summary.expected_cash_in_drawer) }} {{ $t('common.currency') }}</span>
                </div>
            </div>

            <form id="closeShiftForm" @submit.prevent="submitCloseShift" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.actual_cash_field') }}</label>
                    <input
                        v-model="closeShiftForm.actual_cash_balance"
                        type="number"
                        step="0.01"
                        required
                        placeholder="0.00"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm text-emerald-600 dark:text-emerald-400 font-mono font-black focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.close_notes_field') }}</label>
                    <input
                        v-model="closeShiftForm.notes"
                        type="text"
                        :placeholder="$t('invoices.notes')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showCloseShiftModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        form="closeShiftForm"
                        :disabled="closeShiftForm.processing"
                        class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                    >
                        {{ closeShiftForm.processing ? $t('common.save') + '...' : $t('treasury.confirm_close_shift_btn') }}
                    </button>
                </div>
            </template>
        </AppModal>

        <!-- Quick Expense Modal -->
        <AppModal
            :show="showExpenseModal"
            :title="$t('treasury.record_expense_modal')"
            icon="💸"
            max-width="md"
            @close="showExpenseModal = false"
        >
            <form id="expenseForm" @submit.prevent="submitExpense" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.expense_title_field') }}</label>
                    <input
                        v-model="expenseForm.title"
                        type="text"
                        required
                        placeholder="مثال: فاتورة كهرباء / بوفيه ومشروبات..."
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('expenses.amount') }} *</label>
                        <input
                            v-model="expenseForm.amount"
                            type="number"
                            step="0.01"
                            required
                            placeholder="0.00"
                            class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-rose-600 dark:text-rose-400 font-mono font-black focus:border-theme-primary focus:outline-none shadow-inner"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.payment_method_field') }}</label>
                        <SearchableSelect
                            v-model="expenseForm.payment_method"
                            :options="paymentMethodOptions"
                            :placeholder="$t('treasury.payment_method')"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('treasury.cost_center_field') }}</label>
                    <SearchableSelect
                        v-model="expenseForm.cost_center"
                        :options="costCenterOptions"
                        :placeholder="$t('expenses.cost_center')"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.notes') }}</label>
                    <input
                        v-model="expenseForm.notes"
                        type="text"
                        :placeholder="$t('invoices.notes')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                    >
                </div>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                        @click="showExpenseModal = false"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        form="expenseForm"
                        :disabled="expenseForm.processing"
                        class="h-11 px-6 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-black shadow-lg shadow-rose-600/20 transition transform active:scale-95 cursor-pointer disabled:opacity-50"
                    >
                        {{ expenseForm.processing ? $t('common.save') + '...' : $t('treasury.record_expense_modal') }}
                    </button>
                </div>
            </template>
        </AppModal>
    </AppLayout>
</template>