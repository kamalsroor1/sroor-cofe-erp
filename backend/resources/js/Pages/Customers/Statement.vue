<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import StatCardSkeleton from '@/Components/Common/Skeletons/StatCardSkeleton.vue';
import TableSkeleton from '@/Components/Common/Skeletons/TableSkeleton.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    customer: { type: Object, required: true },
    ledger: { type: Array, default: () => [] },
    summary: { type: Object, default: () => null },
    filters: { type: Object, default: () => ({}) },
});

const statementColumns = computed(() => [
    { key: 'date', label: trans('common.date'), mono: true },
    { key: 'type', label: trans('contacts.transaction_type') },
    { key: 'ref_number', label: trans('contacts.reference_no'), mono: true },
    { key: 'debit', label: trans('contacts.period_debit'), mono: true },
    { key: 'credit', label: trans('contacts.period_credit'), mono: true },
    { key: 'balance_after', label: trans('contacts.closing_balance'), mono: true },
    { key: 'notes', label: trans('common.notes') },
]);

const { formatMoney } = useMoney();

const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const activePreset = ref(props.filters.from ? 'custom' : 'all');

const applyDatePreset = (preset) => {
    activePreset.value = preset;
    const now = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        dateFrom.value = formatDate(now);
        dateTo.value = formatDate(now);
    } else if (preset === 'this_month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        dateFrom.value = formatDate(start);
        dateTo.value = formatDate(end);
    } else if (preset === 'this_year') {
        const start = new Date(now.getFullYear(), 0, 1);
        const end = new Date(now.getFullYear(), 11, 31);
        dateFrom.value = formatDate(start);
        dateTo.value = formatDate(end);
    } else if (preset === 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    filterStatement();
};

const filterStatement = () => {
    router.get(`/customers/${props.customer.id}/statement`, {
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const printStatement = () => {
    window.print();
};
</script>

<template>
    <Head :title="`${$t('contacts.ledger_title')}: ${customer.name}`" />

    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-6 font-tajawal">
            <!-- Header & Action Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 no-print">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Link
                            href="/customers"
                            class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-transparent shrink-0"
                            :title="$t('common.back') || 'رجوع'"
                        >
                            →
                        </Link>
                        <div>
                            <h1 class="text-base sm:text-xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5">
                                <span>{{ $t('contacts.ledger_title') }}:</span>
                                <span class="text-theme-primary">{{ customer.name }}</span>
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-bold">
                                {{ $t('contacts.ledger_subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        @click="printStatement"
                        type="button"
                        class="w-full sm:w-auto h-11 px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20 transition active:scale-95 cursor-pointer"
                    >
                        <span>📄</span>
                        <span>{{ $t('contacts.print_statement') }}</span>
                    </button>
                </div>
            </div>

            <!-- Customer Summary Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xs space-y-4 font-tajawal">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.customer_name') }}</span>
                        <div class="text-base font-black text-slate-900 dark:text-white">{{ customer.name }}</div>
                        <div v-if="customer.phone" class="text-xs text-slate-500 dark:text-slate-400 font-mono" dir="ltr">📱 {{ customer.phone }}</div>
                    </div>

                    <div class="space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('common.address') }}</span>
                        <div class="text-xs text-slate-700 dark:text-slate-300 font-bold">{{ customer.address || '—' }}</div>
                        <div v-if="customer.tax_number" class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $t('invoices.tax_number') || 'الرقم الضريبي' }}: {{ customer.tax_number }}</div>
                    </div>

                    <div class="space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('contacts.closing_balance') }}</span>
                        <div
                            class="text-xl font-black font-mono"
                            :class="customer.current_balance > 0 ? 'text-rose-600 dark:text-rose-400' : (customer.current_balance < 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400')"
                        >
                            {{ formatMoney(customer.current_balance) }} <span class="text-xs text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filter & Presets -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-3 no-print">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-slate-500 dark:text-slate-400 font-bold text-[11px] ml-1">{{ $t('contacts.report_period') }}:</span>
                        <button
                            @click="applyDatePreset('today')"
                            type="button"
                            class="h-9 px-3.5 rounded-xl font-bold transition active:scale-95 cursor-pointer shadow-xs"
                            :class="activePreset === 'today' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('common.today') || 'اليوم' }}
                        </button>
                        <button
                            @click="applyDatePreset('this_month')"
                            type="button"
                            class="h-9 px-3.5 rounded-xl font-bold transition active:scale-95 cursor-pointer shadow-xs"
                            :class="activePreset === 'this_month' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('common.this_month') || 'هذا الشهر' }}
                        </button>
                        <button
                            @click="applyDatePreset('this_year')"
                            type="button"
                            class="h-9 px-3.5 rounded-xl font-bold transition active:scale-95 cursor-pointer shadow-xs"
                            :class="activePreset === 'this_year' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('common.this_year') || 'هذا العام' }}
                        </button>
                        <button
                            @click="applyDatePreset('all')"
                            type="button"
                            class="h-9 px-3.5 rounded-xl font-bold transition active:scale-95 cursor-pointer shadow-xs"
                            :class="activePreset === 'all' ? 'bg-theme-primary text-slate-950 font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('common.all') || 'الكل' }}
                        </button>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-2.5 sm:gap-3">
                        <div class="w-full sm:flex-1">
                            <DatePicker v-model="dateFrom" :placeholder="$t('contacts.from_date') || 'من تاريخ'" />
                        </div>
                        <div class="w-full sm:flex-1">
                            <DatePicker v-model="dateTo" :placeholder="$t('contacts.to_date') || 'إلى تاريخ'" />
                        </div>
                        <button
                            @click="activePreset = 'custom'; filterStatement();"
                            type="button"
                            class="w-full sm:w-auto h-11 px-6 rounded-2xl btn-primary-theme text-xs font-black transition active:scale-95 cursor-pointer shadow-theme-primary shrink-0"
                        >
                            {{ $t('common.filter') || 'تصفية' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3 Summary KPI Cards for Statement Period (Bento Grid on Mobile, Deferred with Skeleton) -->
            <Deferred data="summary">
                <template #fallback>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                        <StatCardSkeleton v-for="i in 3" :key="i" />
                    </div>
                </template>

                <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal animate-in fade-in duration-500">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold block">{{ $t('contacts.period_debit') }}</span>
                        <div class="text-lg sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400">
                            {{ formatMoney(summary.total_debit) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold block">{{ $t('contacts.period_credit') }}</span>
                        <div class="text-lg sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                            {{ formatMoney(summary.total_credit) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                        </div>
                    </div>

                    <div class="col-span-2 sm:col-span-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold block">{{ $t('contacts.closing_balance') }}</span>
                        <div class="text-lg sm:text-2xl font-black font-mono text-theme-primary">
                            {{ formatMoney(summary.current_balance) }} <span class="text-[11px] text-slate-700 dark:text-white">{{ $t('common.currency') }}</span>
                        </div>
                    </div>
                </div>
            </Deferred>

            <!-- Ledger Data Table (Deferred with TableSkeleton) -->
            <Deferred data="ledger">
                <template #fallback>
                    <TableSkeleton :columns-count="7" :rows-count="6" />
                </template>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal animate-in fade-in duration-500">
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
                        <span
                            class="px-2 py-0.5 rounded-lg text-[10.5px] font-bold border font-tajawal"
                            :class="row.type.includes('فاتورة') ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/30' : (row.type.includes('قبض') ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30')"
                        >
                            {{ row.type }}
                        </span>
                    </template>

                    <!-- Ref Number -->
                    <template #cell-ref_number="{ row }">
                        <span class="font-mono text-slate-900 dark:text-white font-bold">
                            {{ row.ref_number || '—' }}
                        </span>
                    </template>

                    <!-- Debit -->
                    <template #cell-debit="{ row }">
                        <span class="font-mono font-bold text-rose-600 dark:text-rose-400">
                            {{ row.debit > 0 ? formatMoney(row.debit) : '—' }}
                        </span>
                    </template>

                    <!-- Credit -->
                    <template #cell-credit="{ row }">
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ row.credit > 0 ? formatMoney(row.credit) : '—' }}
                        </span>
                    </template>

                    <!-- Balance After -->
                    <template #cell-balance_after="{ row }">
                        <span class="font-mono font-black text-theme-primary text-sm">
                            {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Notes -->
                    <template #cell-notes="{ row }">
                        <span class="text-slate-500 dark:text-slate-400 text-[11px] font-tajawal">
                            {{ row.notes || '—' }}
                        </span>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-2 py-0.5 rounded-lg text-[10.5px] font-bold border"
                                        :class="row.type.includes('فاتورة') ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/30' : (row.type.includes('قبض') ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30')"
                                    >
                                        {{ row.type }}
                                    </span>
                                    <span v-if="row.ref_number" class="font-mono text-xs font-black text-slate-900 dark:text-white">#{{ row.ref_number }}</span>
                                </div>
                                <span class="font-mono text-[11px] text-slate-400">{{ row.date }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs font-mono py-1">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_debit') }}</span>
                                    <span class="font-bold text-rose-600 dark:text-rose-400">{{ row.debit > 0 ? formatMoney(row.debit) : '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_credit') }}</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ row.credit > 0 ? formatMoney(row.credit) : '—' }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.closing_balance') }}</span>
                                    <span class="font-black text-theme-primary">{{ formatMoney(row.balance_after) }}</span>
                                </div>
                            </div>

                            <div v-if="row.notes" class="text-[11px] text-slate-500 dark:text-slate-400 font-tajawal border-t border-slate-200 dark:border-slate-800/80 pt-1.5">
                                📝 {{ row.notes }}
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
            </Deferred>
        </div>
    </AppLayout>
</template>

