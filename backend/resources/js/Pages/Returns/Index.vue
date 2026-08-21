<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
    returns: { type: Object, required: true },
    metrics: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

const returnColumns = computed(() => [
    { key: 'return_number', label: trans('returns.return_number'), sortable: true, mono: true },
    { key: 'return_type', label: trans('returns.return_type') },
    { key: 'party_name', label: trans('returns.party_name'), sortable: true },
    { key: 'return_date', label: trans('common.date'), mono: true },
    { key: 'net_total', label: trans('common.total'), mono: true },
    { key: 'reason', label: trans('returns.reason') },
    { key: 'actions', label: trans('common.actions'), align: 'center' },
]);

const search = ref(props.filters.search || '');
const type = ref(props.filters.type || 'all');
const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const isDrawerOpen = ref(false);

const typeOptions = computed(() => [
    { id: 'all', name: trans('returns.all_returns') || 'كافة المرتجعات' },
    { id: 'sales_return', name: trans('returns.sales_return') || 'مرتجع مبيعات من عميل ↩️' },
    { id: 'purchase_return', name: trans('returns.purchase_return') || 'مرتجع مشتريات إلى مورد ↪️' },
]);

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (type.value !== 'all') count++;
    if (dateFrom.value || dateTo.value) count++;
    return count;
});

const applyFilters = () => {
    router.get('/returns', {
        search: search.value || undefined,
        type: type.value !== 'all' ? type.value : undefined,
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
    type.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

// Details Modal
const showDetailsModal = ref(false);
const selectedReturn = ref(null);

const openDetailsModal = (r) => {
    selectedReturn.value = r;
    showDetailsModal.value = true;
};

const deleteReturn = (r) => {
    if (confirm(trans('returns.confirm_archive', { number: r.return_number }) || `هل أنت متأكد من أرشفة مستند المرتجع (${r.return_number})؟`)) {
        router.delete(`/returns/${r.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="$t('returns.title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('returns.title')"
                :subtitle="$t('returns.subtitle')"
                icon="🔄"
            >
                <template #actions>
                    <Link
                        href="/returns/create"
                        class="h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-sm"
                    >
                        <span class="text-base font-black">+</span>
                        <span>{{ $t('returns.new_return_btn') }}</span>
                    </Link>
                </template>
            </PageHeader>

            <!-- KPI Summary Cards (Bento Style on mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                <MetricCard
                    :title="$t('returns.total_returns_amount')"
                    :value="formatMoney(metrics.total_amount)"
                    :currency="$t('common.currency')"
                    variant="primary"
                />

                <MetricCard
                    :title="$t('returns.sales_returns_count')"
                    :value="metrics.sales_returns_count || 0"
                    :currency="$t('returns.doc_unit')"
                    variant="danger"
                />

                <MetricCard
                    class="col-span-2 sm:col-span-1"
                    :title="$t('returns.purchase_returns_count')"
                    :value="metrics.purchase_returns_count || 0"
                    :currency="$t('returns.doc_unit')"
                    variant="success"
                />
            </div>

            <!-- Quick Filter Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-3 font-tajawal">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="w-full md:w-96 relative">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('returns.search_placeholder')"
                            class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner font-tajawal"
                        >
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
                            🔍
                        </span>
                    </div>

                    <div class="w-full md:w-auto flex flex-wrap items-center justify-between md:justify-end gap-2">
                        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950/80 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
                            <button
                                @click="type = 'all'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="type === 'all' ? 'tab-theme-active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.all') }}
                            </button>
                            <button
                                @click="type = 'sales_return'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="type === 'sales_return' ? 'bg-rose-500 text-white font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('returns.sales_return') }}
                            </button>
                            <button
                                @click="type = 'purchase_return'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="type === 'purchase_return' ? 'bg-emerald-500 text-slate-950 font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('returns.purchase_return') }}
                            </button>
                        </div>

                        <button
                            @click="isDrawerOpen = true"
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-2 transition cursor-pointer active:scale-95 shadow-xs"
                        >
                            <span>⚙️</span>
                            <span>{{ $t('common.filter') }}</span>
                            <span v-if="activeFiltersCount > 0" class="w-5 h-5 rounded-full btn-primary-theme font-mono font-black text-[11px] flex items-center justify-center">
                                {{ activeFiltersCount }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Returns Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="returnColumns"
                    :rows="returns.data"
                    :pagination="returns"
                    :empty-title="$t('returns.no_returns_found')"
                    empty-icon="🔄"
                >
                    <!-- Number -->
                    <template #cell-return_number="{ row }">
                        <span class="font-mono font-black text-theme-primary">
                            {{ row.return_number }}
                        </span>
                    </template>

                    <!-- Type -->
                    <template #cell-return_type="{ row }">
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal"
                            :class="row.return_type === 'sales_return' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'"
                        >
                            {{ row.return_type === 'sales_return' ? $t('returns.sales_return') : $t('returns.purchase_return') }}
                        </span>
                    </template>

                    <!-- Party Name -->
                    <template #cell-party_name="{ row }">
                        <span class="font-bold text-slate-900 dark:text-white font-tajawal">
                            {{ row.party_name }}
                        </span>
                    </template>

                    <!-- Date -->
                    <template #cell-return_date="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.return_date }}
                        </span>
                    </template>

                    <!-- Total -->
                    <template #cell-net_total="{ row }">
                        <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
                            {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Reason -->
                    <template #cell-reason="{ row }">
                        <span class="font-tajawal text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.reason || '—' }}
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center gap-1.5 font-tajawal">
                            <button
                                @click="openDetailsModal(row)"
                                type="button"
                                class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
                            >
                                {{ $t('returns.details_btn', { count: row.items_count }) }}
                            </button>

                            <button
                                @click="deleteReturn(row)"
                                type="button"
                                class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition cursor-pointer"
                                :title="$t('common.delete')"
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
                                    <div class="font-mono font-black text-sm text-theme-primary">{{ row.return_number }}</div>
                                    <p class="text-[11px] text-slate-900 dark:text-white font-bold">{{ row.party_name }}</p>
                                </div>

                                <span class="font-mono font-black text-sm text-slate-900 dark:text-white">
                                    {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="row.return_type === 'sales_return' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'"
                                >
                                    {{ row.return_type === 'sales_return' ? $t('returns.sales_return') : $t('returns.purchase_return') }}
                                </span>
                                <span class="text-[11px] text-slate-400 font-mono">{{ row.return_date }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-200 dark:border-slate-800/80">
                                <button
                                    @click="openDetailsModal(row)"
                                    type="button"
                                    class="flex-1 h-10 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-xs border border-slate-200 dark:border-slate-700"
                                >
                                    <span>📋</span>
                                    <span>{{ $t('returns.details_btn', { count: row.items_count }) }}</span>
                                </button>

                                <button
                                    @click="deleteReturn(row)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs shrink-0"
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
                        :placeholder="$t('common.search')"
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none transition"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300">🔄 {{ $t('returns.return_type') }}</label>
                    <SearchableSelect
                        v-model="type"
                        :options="typeOptions"
                        :placeholder="$t('returns.return_type')"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('contacts.from_date') }}</label>
                        <DatePicker v-model="dateFrom" :placeholder="$t('contacts.from_date')" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('contacts.to_date') }}</label>
                        <DatePicker v-model="dateTo" :placeholder="$t('contacts.to_date')" />
                    </div>
                </div>
            </div>
        </FilterDrawer>

        <!-- Return Details Modal (Smooth Native Pop) -->
        <Teleport to="body">
            <Transition name="modal-zoom">
                <div
                    v-if="showDetailsModal && selectedReturn"
                    @click="showDetailsModal = false"
                    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
                    dir="rtl"
                >
                    <div @click.stop class="w-full max-w-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 text-slate-900 dark:text-white max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-black text-base text-slate-900 dark:text-white">{{ $t('returns.return_details') }}: {{ selectedReturn.return_number }}</h3>
                                <p class="text-xs text-theme-primary font-bold mt-0.5">{{ selectedReturn.party_name }} | {{ selectedReturn.return_date }}</p>
                            </div>
                            <button
                                @click="showDetailsModal = false"
                                class="w-9 h-9 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-400 text-xs hover:text-slate-900 dark:hover:text-white cursor-pointer flex items-center justify-center transition active:scale-90 shadow-xs"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-right text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                        <th class="pb-2">{{ $t('inventory.item_name') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('common.quantity') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('invoices.unit_price') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('common.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                                    <tr v-for="it in selectedReturn.items" :key="it.id">
                                        <td class="py-2.5 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.item_name }}</td>
                                        <td class="py-2.5 font-mono font-black text-theme-primary">{{ it.quantity }}</td>
                                        <td class="py-2.5 font-mono text-slate-600 dark:text-slate-300">{{ formatMoney(it.unit_price) }}</td>
                                        <td class="py-2.5 font-mono font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(it.subtotal) }} {{ $t('common.currency') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 bg-slate-50 dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between font-mono">
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('returns.total_returns_val') }}:</span>
                            <span class="text-lg font-black text-theme-primary">{{ formatMoney(selectedReturn.net_total) }} {{ $t('common.currency') }}</span>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>