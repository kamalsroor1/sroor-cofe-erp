<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FilterDrawer from '@/Components/FilterDrawer.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    expenses: { type: Object, required: true },
    metrics: { type: Object, default: () => ({}) },
    cost_centers: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || 'all');
const costCenter = ref(props.filters.cost_center || 'all');
const paymentMethod = ref(props.filters.payment_method || 'all');
const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const isDrawerOpen = ref(false);

const quickCategories = computed(() => [
    trans('expenses.preset_packaging') || 'شنط وأكياس وتغليف',
    'أكواب ورقية وبلاستيكية',
    'لاصق وشرائط تغليف',
    trans('expenses.cc_hospitality') || 'بوفيه وضيافة',
    trans('expenses.cc_maintenance') || 'صيانة مطاحن ومعدات',
    trans('expenses.cc_rent') || 'إيجار وكهرباء ومرافق',
    trans('expenses.cc_operational') || 'نثريات ومصاريف تشغيل',
]);

const costCenterOptions = computed(() => {
    return [
        { id: 'all', name: trans('expenses.all_cost_centers') || 'كافة مراكز التكلفة' },
        ...Object.entries(props.cost_centers).map(([k, v]) => ({ id: k, name: v }))
    ];
});

const paymentMethodOptions = computed(() => [
    { id: 'all', name: trans('expenses.all_payment_methods') || 'كافة طرق الدفع' },
    { id: 'cash', name: `${trans('treasury.cash_drawer') || 'نقداً كاش'} 💵` },
    { id: 'instapay', name: `${trans('treasury.instapay') || 'انستاباي'} ⚡` },
    { id: 'e_wallet', name: `${trans('treasury.e_wallet') || 'محفظة إلكترونية'} 📱` },
    { id: 'visa', name: `${trans('treasury.visa') || 'فيزا وبطاقة بنكية'} 💳` },
    { id: 'bank_transfer', name: `${trans('treasury.bank_transfer') || 'تحويل بنكي'} 🏦` },
    { id: 'check', name: 'شيك 📄' },
]);

const expenseColumns = computed(() => [
    { key: 'expense_number', label: trans('invoices.invoice_number'), sortable: true, mono: true },
    { key: 'title', label: trans('expenses.expense_item') },
    { key: 'cost_center', label: `${trans('expenses.cost_center')} & ${trans('expenses.category')}` },
    { key: 'expense_date', label: trans('common.date'), mono: true },
    { key: 'amount', label: trans('common.amount'), mono: true },
    { key: 'payment_method', label: trans('invoices.payment_method') },
    { key: 'actions', label: trans('common.actions'), align: 'center' },
]);

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (category.value !== 'all') count++;
    if (costCenter.value !== 'all') count++;
    if (paymentMethod.value !== 'all') count++;
    if (dateFrom.value || dateTo.value) count++;
    return count;
});

const applyFilters = () => {
    router.get('/expenses', {
        search: search.value || undefined,
        category: category.value !== 'all' ? category.value : undefined,
        cost_center: costCenter.value !== 'all' ? costCenter.value : undefined,
        payment_method: paymentMethod.value !== 'all' ? paymentMethod.value : undefined,
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            isDrawerOpen.value = false;
        }
    });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters();
    }, 400);
});

const resetFilters = () => {
    search.value = '';
    category.value = 'all';
    costCenter.value = 'all';
    paymentMethod.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

// Add / Edit Modal
const showModal = ref(false);
const editingExpense = ref(null);

const expenseForm = useForm({
    title: '',
    category: 'نثريات ومصاريف تشغيل',
    cost_center: 'operational',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    payment_method: 'cash',
    notes: '',
});

const openCreateModal = () => {
    editingExpense.value = null;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.expense_date = new Date().toISOString().split('T')[0];
    showModal.value = true;
};

const openEditModal = (e) => {
    editingExpense.value = e;
    expenseForm.clearErrors();
    expenseForm.title = e.title;
    expenseForm.category = e.category;
    expenseForm.cost_center = e.cost_center;
    expenseForm.amount = e.amount;
    expenseForm.expense_date = e.expense_date;
    expenseForm.payment_method = e.payment_method;
    expenseForm.notes = e.notes || '';
    showModal.value = true;
};

const saveExpense = () => {
    if (editingExpense.value) {
        expenseForm.put(`/expenses/${editingExpense.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            }
        });
    } else {
        expenseForm.post('/expenses', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            }
        });
    }
};

const deleteExpense = (e) => {
    const confirmMsg = trans('expenses.delete_confirm', { title: e.title }) || `هل أنت متأكد من حذف المصروف (${e.title})؟`;
    if (confirm(confirmMsg)) {
        router.delete(`/expenses/${e.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="$t('expenses.title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('expenses.title')"
                :subtitle="$t('expenses.expenses_breakdown')"
                icon="💸"
            >
                <template #actions>
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-sm"
                    >
                        <span class="text-base font-black">+</span>
                        <span>{{ $t('expenses.add_expense') }}</span>
                    </button>
                </template>
            </PageHeader>

            <!-- KPI Summary Cards (Bento Style on mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4">
                <MetricCard
                    :title="$t('expenses.total_today')"
                    :value="formatMoney(metrics.total_today)"
                    :currency="$t('common.currency')"
                    variant="danger"
                />

                <MetricCard
                    :title="$t('expenses.total_cash')"
                    :value="formatMoney(metrics.total_cash)"
                    :currency="$t('common.currency')"
                    variant="primary"
                />

                <MetricCard
                    class="col-span-2 sm:col-span-1"
                    :title="$t('expenses.total_filtered')"
                    :value="formatMoney(metrics.total_filtered)"
                    :currency="$t('common.currency')"
                    variant="primary"
                />
            </div>

            <!-- Quick Filter Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="w-full md:w-96 relative">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('expenses.search_placeholder')"
                            class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner font-tajawal"
                        >
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
                            🔍
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="isDrawerOpen = true"
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-2 transition cursor-pointer active:scale-95 shadow-xs"
                        >
                            <span>⚙️</span>
                            <span>{{ $t('common.advanced_filter') }}</span>
                            <span v-if="activeFiltersCount > 0" class="w-5 h-5 rounded-full btn-primary-theme font-mono font-black text-[11px] flex items-center justify-center">
                                {{ activeFiltersCount }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Expenses Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="expenseColumns"
                    :rows="expenses.data"
                    :pagination="expenses"
                    :empty-title="$t('expenses.no_expenses')"
                    empty-icon="💸"
                >
                    <!-- Expense Number -->
                    <template #cell-expense_number="{ row }">
                        <span class="font-mono font-bold text-theme-primary">
                            {{ row.expense_number }}
                        </span>
                    </template>

                    <!-- Title -->
                    <template #cell-title="{ row }">
                        <div class="font-black text-slate-900 dark:text-white font-tajawal">{{ row.title }}</div>
                        <div v-if="row.notes" class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal">{{ row.notes }}</div>
                    </template>

                    <!-- Cost Center & Category -->
                    <template #cell-cost_center="{ row }">
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold border border-slate-200 dark:border-transparent">
                            {{ row.cost_center_label }}
                        </span>
                        <div class="text-[10px] text-theme-primary font-bold mt-0.5">{{ row.category }}</div>
                    </template>

                    <!-- Date -->
                    <template #cell-expense_date="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.expense_date }}
                        </span>
                    </template>

                    <!-- Amount -->
                    <template #cell-amount="{ row }">
                        <span class="font-mono font-black text-rose-600 dark:text-rose-400 text-sm">
                            {{ formatMoney(row.amount) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Payment Method -->
                    <template #cell-payment_method="{ row }">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-transparent font-tajawal">
                            {{ row.payment_method === 'cash' ? `${$t('treasury.cash_drawer')} 💵` : row.payment_method }}
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center gap-1.5 font-tajawal">
                            <button
                                @click="openEditModal(row)"
                                type="button"
                                class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer border border-slate-200 dark:border-transparent"
                            >
                                {{ $t('common.edit') }} ✏️
                            </button>

                            <button
                                @click="deleteExpense(row)"
                                type="button"
                                class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition cursor-pointer"
                            >
                                🗑️
                            </button>
                        </div>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs font-tajawal">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2.5">
                                <div class="space-y-0.5">
                                    <div class="font-black text-sm text-slate-900 dark:text-white">{{ row.title }}</div>
                                    <p class="text-[11px] text-slate-400 font-mono">{{ row.expense_number }} • {{ row.expense_date }}</p>
                                </div>

                                <span class="font-mono font-black text-sm text-rose-600 dark:text-rose-400">
                                    {{ formatMoney(row.amount) }} {{ $t('common.currency') }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold">
                                        {{ row.cost_center_label }}
                                    </span>
                                    <span class="text-theme-primary font-bold text-[11px]">{{ row.category }}</span>
                                </div>

                                <span class="text-[10px] font-bold text-slate-500">
                                    {{ row.payment_method === 'cash' ? '💵 كاش' : row.payment_method }}
                                </span>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button
                                    @click="openEditModal(row)"
                                    type="button"
                                    class="h-10 px-4 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 text-xs font-bold transition active:scale-95 cursor-pointer flex items-center gap-1.5 shadow-xs"
                                >
                                    <span>✏️</span>
                                    <span>{{ $t('common.edit') }}</span>
                                </button>

                                <button
                                    @click="deleteExpense(row)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs"
                                    :title="$t('common.delete')"
                                >
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>

        </div>

        <!-- Filter Slide-Over Drawer -->
        <FilterDrawer
            :show="isDrawerOpen"
            :active-count="activeFiltersCount"
            @close="isDrawerOpen = false"
            @apply="applyFilters"
            @reset="resetFilters"
        >
            <div class="space-y-5">
                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300">🔍 {{ $t('common.search') }}</label>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('expenses.search_placeholder')"
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none transition"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300">🏢 {{ $t('expenses.cost_center') }}</label>
                    <SearchableSelect
                        v-model="costCenter"
                        :options="costCenterOptions"
                        :placeholder="$t('expenses.cost_center')"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300">💳 {{ $t('invoices.payment_method') }}</label>
                    <SearchableSelect
                        v-model="paymentMethod"
                        :options="paymentMethodOptions"
                        :placeholder="$t('invoices.payment_method')"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('common.date_from') }}</label>
                        <DatePicker v-model="dateFrom" :placeholder="$t('common.date_from')" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('common.date_to') }}</label>
                        <DatePicker v-model="dateTo" :placeholder="$t('common.date_to')" />
                    </div>
                </div>
            </div>
        </FilterDrawer>

        <!-- Add / Edit Expense Modal (Smooth Native Pop) -->
        <Teleport to="body">
            <Transition name="modal-zoom">
                <div
                    v-if="showModal"
                    @click="showModal = false"
                    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
                    dir="rtl"
                >
                    <div @click.stop class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 text-slate-900 dark:text-white max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <h3 class="font-black text-base text-slate-900 dark:text-white">
                                {{ editingExpense ? $t('expenses.edit_expense') : $t('expenses.new_expense') }}
                            </h3>
                            <button
                                @click="showModal = false"
                                class="w-9 h-9 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-400 text-xs hover:text-slate-900 dark:hover:text-white cursor-pointer flex items-center justify-center transition active:scale-90 shadow-xs"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <form @submit.prevent="saveExpense" class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('expenses.expense_item') }} *</label>
                                <input
                                    v-model="expenseForm.title"
                                    type="text"
                                    required
                                    placeholder="مثال: شراء كراتين شحن / صيانة طاحونة رقم 2..."
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('expenses.amount') }} *</label>
                                    <input
                                        v-model.number="expenseForm.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="0.00"
                                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-mono font-black text-rose-600 dark:text-rose-400 focus:border-theme-primary focus:outline-none shadow-inner"
                                    >
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('common.date') }} *</label>
                                    <DatePicker v-model="expenseForm.expense_date" :placeholder="$t('common.date')" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('expenses.cost_center') }} *</label>
                                    <select
                                        v-model="expenseForm.cost_center"
                                        class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-theme-primary focus:outline-none shadow-inner font-bold"
                                    >
                                        <option v-for="(label, key) in cost_centers" :key="key" :value="key">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.payment_method') }} *</label>
                                    <select
                                        v-model="expenseForm.payment_method"
                                        class="w-full h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-theme-primary focus:outline-none shadow-inner font-bold"
                                    >
                                        <option value="cash">{{ $t('treasury.cash_drawer') }} 💵</option>
                                        <option value="instapay">{{ $t('treasury.instapay') }} ⚡</option>
                                        <option value="e_wallet">{{ $t('treasury.e_wallet') }} 📱</option>
                                        <option value="visa">{{ $t('treasury.visa') }} 💳</option>
                                        <option value="bank_transfer">{{ $t('treasury.bank_transfer') }} 🏦</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('expenses.quick_category') }}</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="c in quickCategories"
                                        :key="c"
                                        @click="expenseForm.category = c"
                                        type="button"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold border transition active:scale-95 cursor-pointer shadow-xs"
                                        :class="expenseForm.category === c ? 'bg-theme-light text-theme-primary border-theme-primary' : 'bg-slate-100 dark:bg-slate-950 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:text-slate-900 dark:hover:text-white'"
                                    >
                                        {{ c }}
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('invoices.notes') }}</label>
                                <input
                                    v-model="expenseForm.notes"
                                    type="text"
                                    :placeholder="$t('invoices.notes')"
                                    class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                                >
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    @click="showModal = false"
                                    type="button"
                                    class="h-11 px-5 rounded-2xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 cursor-pointer shadow-xs"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="expenseForm.processing"
                                    class="h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition transform active:scale-95 cursor-pointer disabled:opacity-50 shadow-theme-primary"
                                >
                                    {{ expenseForm.processing ? $t('common.save') + '...' : $t('common.save') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>