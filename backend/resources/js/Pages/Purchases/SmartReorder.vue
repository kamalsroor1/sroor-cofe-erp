<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    suggestions: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
    stores: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

const reorderColumns = computed(() => [
    { key: 'name', label: trans('inventory.item_name'), sortable: true },
    { key: 'current_stock', label: trans('inventory.current_stock'), mono: true },
    { key: 'analysis_sales', label: trans('purchases.sales_period', { days: analysisDays.value }), mono: true },
    { key: 'daily_consumption', label: trans('purchases.daily_usage'), mono: true },
    { key: 'days_remaining', label: trans('purchases.days_of_stock'), mono: true },
    { key: 'suggested_quantity', label: trans('purchases.suggested_qty'), mono: true },
    { key: 'estimated_cost', label: trans('purchases.estimated_cost'), mono: true },
    { key: 'urgency', label: trans('common.status'), align: 'center' },
]);

const search = ref(props.filters.search || '');
const selectedStoreId = ref(props.filters.store_id || 'all');
const analysisDays = ref(props.filters.analysis_days || 14);
const targetCoverDays = ref(props.filters.target_cover_days || 15);
const urgencyFilter = ref(props.filters.urgency || 'all');

const selectedItemIds = ref([]);

const applyFilters = () => {
    router.get('/purchases/smart-reorder', {
        search: search.value || undefined,
        store_id: selectedStoreId.value !== 'all' ? selectedStoreId.value : undefined,
        analysis_days: analysisDays.value,
        target_cover_days: targetCoverDays.value,
        urgency: urgencyFilter.value !== 'all' ? urgencyFilter.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const selectAllCritical = () => {
    const criticalIds = props.suggestions
        .filter(it => it.urgency === 'critical' || it.urgency === 'warning')
        .map(it => it.id);
    selectedItemIds.value = criticalIds;
};

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedItemIds.value = props.suggestions.map(it => it.id);
    } else {
        selectedItemIds.value = [];
    }
};

const createPurchaseFromSelected = () => {
    if (selectedItemIds.value.length === 0) {
        alert(trans('purchases.select_one_item_warning') || 'يرجى تحديد صنف واحد على الأقل لإنشاء فاتورة الشراء والتوريد');
        return;
    }

    const prefillData = props.suggestions
        .filter(it => selectedItemIds.value.includes(it.id))
        .map(it => ({
            item_id: it.id,
            quantity: Number(it.suggested_quantity) > 0 ? Number(it.suggested_quantity) : 10,
        }));

    router.get('/purchases/create', {
        prefill: JSON.stringify(prefillData),
    });
};
</script>

<template>
    <Head :title="$t('purchases.smart_reorder')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Link href="/purchases" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-transparent">
                            →
                        </Link>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>🧠 {{ $t('purchases.smart_reorder') }}</span>
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mt-0.5">
                                {{ $t('purchases.smart_reorder_sub') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                    <button
                        @click="selectAllCritical"
                        type="button"
                        class="flex-1 sm:flex-none h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-theme-primary border border-slate-200 dark:border-slate-700 text-xs font-bold transition active:scale-95 cursor-pointer shadow-xs"
                    >
                        ⚡ {{ $t('purchases.select_all_critical') }}
                    </button>

                    <button
                        @click="createPurchaseFromSelected"
                        :disabled="selectedItemIds.length === 0"
                        type="button"
                        class="flex-1 sm:flex-none h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-theme-primary"
                    >
                        <span>📥</span>
                        <span>{{ $t('purchases.generate_po_for_selected', { count: selectedItemIds.length }) }}</span>
                    </button>
                </div>
            </div>

            <!-- Top Analytics Metric Cards (Bento Grid on Mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 font-tajawal">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.critical_stockouts') }}</span>
                        <span class="text-sm">🚨</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black font-mono text-rose-600 dark:text-rose-400">
                        {{ metrics.critical_count || 0 }}
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.warning_stockouts') }}</span>
                        <span class="text-sm">⚠️</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black font-mono text-theme-primary">
                        {{ metrics.warning_count || 0 }}
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.safe_stockouts') }}</span>
                        <span class="text-sm">✅</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">
                        {{ metrics.safe_count || 0 }}
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('purchases.total_reorder_cost') }}</span>
                        <span class="text-sm">💰</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white">
                        {{ formatMoney(metrics.total_estimated_cost || 0) }} <span class="text-[11px] text-theme-primary">{{ $t('common.currency') }}</span>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 font-tajawal">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Urgency Filter Tabs -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs font-bold w-full sm:w-auto overflow-x-auto">
                        <button
                            @click="urgencyFilter = 'all'; applyFilters();"
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="urgencyFilter === 'all' ? 'tab-theme-active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('common.all') }} ({{ suggestions.length }})
                        </button>
                        <button
                            @click="urgencyFilter = 'critical'; applyFilters();"
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="urgencyFilter === 'critical' ? 'bg-rose-500 text-white font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('purchases.status_critical') }} 🚨
                        </button>
                        <button
                            @click="urgencyFilter = 'warning'; applyFilters();"
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="urgencyFilter === 'warning' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('purchases.status_warning') }} ⚠️
                        </button>
                        <button
                            @click="urgencyFilter = 'safe'; applyFilters();"
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="urgencyFilter === 'safe' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            {{ $t('purchases.status_safe') }} ✅
                        </button>
                    </div>

                    <!-- Store Filter -->
                    <select
                        v-if="stores.length > 0"
                        v-model="selectedStoreId"
                        @change="applyFilters"
                        class="h-11 px-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-slate-200 focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                        <option value="all">{{ $t('common.all_stores') }}</option>
                        <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                    </select>

                    <!-- Analysis Period & Target Days -->
                    <div class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 font-bold">
                        <span>{{ $t('purchases.daily_consumption') }}:</span>
                        <select
                            v-model.number="analysisDays"
                            @change="applyFilters"
                            class="h-11 px-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none shadow-inner"
                        >
                            <option :value="7">{{ $t('purchases.days_count', { count: 7 }) }}</option>
                            <option :value="14">{{ $t('purchases.days_count', { count: 14 }) }}</option>
                            <option :value="30">{{ $t('purchases.days_count', { count: 30 }) }}</option>
                        </select>

                        <span>{{ $t('purchases.target_cover_days') }}:</span>
                        <select
                            v-model.number="targetCoverDays"
                            @change="applyFilters"
                            class="h-11 px-3 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none shadow-inner"
                        >
                            <option :value="7">{{ $t('purchases.days_count', { count: 7 }) }}</option>
                            <option :value="15">{{ $t('purchases.days_count', { count: 15 }) }}</option>
                            <option :value="30">{{ $t('purchases.days_count', { count: 30 }) }}</option>
                            <option :value="45">{{ $t('purchases.days_count', { count: 45 }) }}</option>
                        </select>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="w-full md:w-64">
                    <input
                        v-model="search"
                        @input="applyFilters"
                        type="text"
                        :placeholder="$t('purchases.search_placeholder')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none shadow-inner"
                    >
                </div>
            </div>

            <!-- Reorder Suggestions Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="reorderColumns"
                    :rows="suggestions"
                    :selectable="true"
                    v-model="selectedItemIds"
                    select-key="id"
                    :empty-title="$t('purchases.empty_reorder_title')"
                    :empty-message="$t('purchases.empty_reorder_subtitle')"
                    empty-icon="✨"
                >
                    <!-- Name -->
                    <template #cell-name="{ row }">
                        <div class="font-black text-slate-900 dark:text-white font-tajawal">{{ row.name }}</div>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ row.code }}</div>
                    </template>

                    <!-- Current Stock -->
                    <template #cell-current_stock="{ row }">
                        <span
                            class="px-2.5 py-1 rounded-xl text-xs border font-mono font-black"
                            :class="[
                                Number(row.current_stock) <= 0 ? 'bg-rose-500/20 border-rose-500/30 text-rose-600 dark:text-rose-400' :
                                (row.urgency === 'critical' ? 'bg-rose-500/10 border-rose-500/20 text-rose-600 dark:text-rose-300' :
                                (row.urgency === 'warning' ? 'bg-amber-500/20 border-amber-500/30 text-amber-600 dark:text-amber-400' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'))
                            ]"
                        >
                            {{ row.current_stock }} {{ row.unit || $t('inventory.unit_kg') || 'كجم' }}
                        </span>
                    </template>

                    <!-- Analysis Sales -->
                    <template #cell-analysis_sales="{ row }">
                        <span class="font-mono text-slate-700 dark:text-slate-300 font-bold">
                            {{ row.analysis_sales }} {{ row.unit || $t('inventory.unit_kg') || 'كجم' }}
                        </span>
                    </template>

                    <!-- Daily Consumption -->
                    <template #cell-daily_consumption="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400">
                            {{ row.daily_consumption }} / {{ $t('common.day') || 'يوم' }}
                        </span>
                    </template>

                    <!-- Days Remaining -->
                    <template #cell-days_remaining="{ row }">
                        <span class="font-mono font-bold" :class="row.days_remaining <= 3 ? 'text-rose-600 dark:text-rose-400 font-black' : (row.days_remaining <= 7 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400')">
                            {{ row.days_remaining === 999 ? $t('purchases.unlimited_days') : $t('purchases.days_count', { count: row.days_remaining }) }}
                        </span>
                    </template>

                    <!-- Suggested Quantity -->
                    <template #cell-suggested_quantity="{ row }">
                        <span class="font-mono font-black text-emerald-600 dark:text-emerald-400 text-sm">
                            {{ row.suggested_quantity }} {{ row.unit || $t('inventory.unit_kg') || 'كجم' }}
                        </span>
                    </template>

                    <!-- Estimated Cost -->
                    <template #cell-estimated_cost="{ row }">
                        <span class="font-mono font-bold text-slate-900 dark:text-white">
                            {{ formatMoney(row.estimated_cost) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Urgency Status -->
                    <template #cell-urgency="{ row }">
                        <span
                            v-if="row.urgency === 'critical'"
                            class="px-2.5 py-1 rounded-xl bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold text-[11px] font-tajawal"
                        >
                            {{ $t('purchases.status_critical') }} 🚨
                        </span>
                        <span
                            v-else-if="row.urgency === 'warning'"
                            class="px-2.5 py-1 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold text-[11px] font-tajawal"
                        >
                            {{ $t('purchases.status_warning') }} ⚠️
                        </span>
                        <span
                            v-else
                            class="px-2.5 py-1 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold text-[11px] font-tajawal"
                        >
                            {{ $t('purchases.status_safe') }} ✅
                        </span>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :value="row.id"
                                        v-model="selectedItemIds"
                                        class="rounded accent-amber-500 w-4 h-4 cursor-pointer"
                                    >
                                    <div>
                                        <div class="font-black text-xs text-slate-900 dark:text-white">{{ row.name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ row.code || '—' }}</div>
                                    </div>
                                </div>

                                <span
                                    v-if="row.urgency === 'critical'"
                                    class="px-2 py-0.5 rounded-lg bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold text-[10px]"
                                >
                                    {{ $t('purchases.status_critical') }} 🚨
                                </span>
                                <span
                                    v-else-if="row.urgency === 'warning'"
                                    class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold text-[10px]"
                                >
                                    {{ $t('purchases.status_warning') }} ⚠️
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold text-[10px]"
                                >
                                    {{ $t('purchases.status_safe') }} ✅
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('inventory.current_stock') }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ row.current_stock }} {{ row.unit }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('purchases.days_of_stock') }}</span>
                                    <span class="font-black" :class="row.days_remaining <= 3 ? 'text-rose-600' : 'text-slate-900 dark:text-white'">
                                        {{ row.days_remaining === 999 ? '∞' : `${row.days_remaining} ${$t('common.day')}` }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-800/80 pt-2 text-xs font-mono">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('purchases.suggested_qty') }}</span>
                                    <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ row.suggested_quantity }} {{ row.unit }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('purchases.estimated_cost') }}</span>
                                    <span class="font-black text-slate-900 dark:text-white">{{ formatMoney(row.estimated_cost) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

