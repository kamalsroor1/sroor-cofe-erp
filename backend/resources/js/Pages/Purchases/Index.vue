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
    purchases: { type: Object, required: true },
    metrics: { type: Object, default: () => ({}) },
    suppliers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

const purchaseColumns = computed(() => [
    { key: 'purchase_number', label: trans('invoices.invoice_number'), sortable: true, mono: true },
    { key: 'supplier_name', label: trans('contacts.supplier_title'), sortable: true },
    { key: 'store_name', label: trans('inventory.store') },
    { key: 'purchase_date', label: trans('common.date'), mono: true },
    { key: 'net_total', label: trans('invoices.grand_total'), mono: true },
    { key: 'paid_amount', label: trans('invoices.paid'), mono: true },
    { key: 'remaining_amount', label: trans('invoices.remaining'), mono: true },
    { key: 'status', label: trans('common.status'), align: 'center' },
    { key: 'actions', label: trans('common.actions'), align: 'center' },
]);

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const supplierId = ref(props.filters.supplier_id || 'all');
const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const isDrawerOpen = ref(false);

const statusOptions = computed(() => [
    { id: 'all', name: trans('common.all') || 'كافة الحالات' },
    { id: 'confirmed', name: `${trans('invoices.status_confirmed') || 'مؤكدة ومستلمة بالمخزن'} 🟢` },
    { id: 'cancelled', name: `${trans('invoices.status_cancelled') || 'ملغاة'} 🔴` },
]);

const supplierOptions = computed(() => {
    return [
        { id: 'all', name: trans('suppliers.all_suppliers') || 'كافة الموردين' },
        ...props.suppliers
    ];
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (status.value !== 'all') count++;
    if (supplierId.value && supplierId.value !== 'all') count++;
    if (dateFrom.value || dateTo.value) count++;
    return count;
});

const applyFilters = () => {
    router.get('/purchases', {
        search: search.value || undefined,
        status: status.value !== 'all' ? status.value : undefined,
        supplier_id: supplierId.value !== 'all' ? supplierId.value : undefined,
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
    status.value = 'all';
    supplierId.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

// Details Modal
const showDetailsModal = ref(false);
const selectedPurchase = ref(null);

const openDetailsModal = (p) => {
    selectedPurchase.value = p;
    showDetailsModal.value = true;
};

const cancelPurchase = (p) => {
    const msg = (trans('purchases.cancel_confirm') || 'هل أنت متأكد من إلغاء فاتورة الشراء (:number)؟').replace(':number', p.purchase_number);
    if (confirm(msg)) {
        router.post(`/purchases/${p.id}/cancel`, {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="$t('purchases.purchases_list')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('purchases.title')"
                :subtitle="$t('purchases.subtitle')"
                icon="📦"
            >
                <template #actions>
                    <Link
                        href="/purchases/smart-reorder"
                        class="h-11 px-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-amber-600 dark:text-amber-400 text-xs font-bold flex items-center gap-1.5 transition active:scale-95 shadow-xs"
                    >
                        <span>🧠</span>
                        <span>{{ $t('purchases.smart_reorder') }}</span>
                    </Link>

                    <Link
                        href="/purchases/create"
                        class="h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-sm"
                    >
                        <span class="text-base font-black">+</span>
                        <span>{{ $t('purchases.create_po_title') }}</span>
                    </Link>
                </template>
            </PageHeader>

            <!-- KPI Summary Cards (Bento Style on mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                <MetricCard
                    :title="$t('purchases.kpi_total_purchases')"
                    :value="formatMoney(metrics.total_purchases)"
                    :currency="$t('common.currency')"
                    variant="primary"
                />

                <MetricCard
                    :title="$t('purchases.kpi_confirmed_count')"
                    :value="metrics.confirmed_count || 0"
                    :currency="$t('invoices.title')"
                    variant="success"
                />

                <MetricCard
                    class="col-span-2 sm:col-span-1"
                    :title="$t('purchases.kpi_unpaid_total')"
                    :value="formatMoney(metrics.unpaid_total)"
                    :currency="$t('common.currency')"
                    variant="danger"
                />
            </div>

            <!-- Quick Filter Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-3 font-tajawal">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="w-full md:w-96 relative">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('purchases.search_placeholder')"
                            class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner font-tajawal"
                        >
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
                            🔍
                        </span>
                    </div>

                    <div class="w-full md:w-auto flex flex-wrap items-center justify-between md:justify-end gap-2">
                        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950/80 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
                            <button
                                @click="status = 'all'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="status === 'all' ? 'tab-theme-active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('common.all') }}
                            </button>
                            <button
                                @click="status = 'confirmed'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="status === 'confirmed' ? 'bg-emerald-500 text-slate-950 font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('invoices.status_confirmed') }} 🟢
                            </button>
                            <button
                                @click="status = 'cancelled'; applyFilters();"
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer active:scale-95"
                                :class="status === 'cancelled' ? 'bg-rose-500 text-white font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                {{ $t('invoices.status_cancelled') }} 🔴
                            </button>
                        </div>

                        <button
                            @click="isDrawerOpen = true"
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-2 transition cursor-pointer active:scale-95 shadow-xs"
                        >
                            <span>⚙️</span>
                            <span>{{ $t('invoices.advanced_filters') }}</span>
                            <span v-if="activeFiltersCount > 0" class="w-5 h-5 rounded-full btn-primary-theme font-mono font-black text-[11px] flex items-center justify-center">
                                {{ activeFiltersCount }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Purchases Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="purchaseColumns"
                    :rows="purchases.data"
                    :pagination="purchases"
                    :empty-title="$t('purchases.no_purchases_found')"
                    empty-icon="📦"
                >
                    <!-- Number -->
                    <template #cell-purchase_number="{ row }">
                        <Link :href="`/purchases/${row.id}`" class="font-mono font-black text-theme-primary hover:underline">
                            #{{ row.purchase_number }}
                        </Link>
                    </template>

                    <!-- Supplier -->
                    <template #cell-supplier_name="{ row }">
                        <div class="font-black text-slate-900 dark:text-white font-tajawal">{{ row.supplier_name }}</div>
                        <div v-if="row.company_name" class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal">{{ row.company_name }}</div>
                    </template>

                    <!-- Store -->
                    <template #cell-store_name="{ row }">
                        <span class="text-slate-600 dark:text-slate-400 font-tajawal">
                            {{ row.store_name || '—' }}
                        </span>
                    </template>

                    <!-- Date -->
                    <template #cell-purchase_date="{ row }">
                        <span class="font-mono text-slate-600 dark:text-slate-300 text-[11px]">
                            {{ row.purchase_date }}
                        </span>
                    </template>

                    <!-- Net Total -->
                    <template #cell-net_total="{ row }">
                        <span class="font-mono font-black text-slate-900 dark:text-white">
                            {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
                        </span>
                    </template>

                    <!-- Paid -->
                    <template #cell-paid_amount="{ row }">
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                            {{ formatMoney(row.paid_amount) }}
                        </span>
                    </template>

                    <!-- Remaining -->
                    <template #cell-remaining_amount="{ row }">
                        <span class="font-mono font-bold text-rose-600 dark:text-rose-400">
                            {{ row.remaining_amount > 0 ? formatMoney(row.remaining_amount) : '—' }}
                        </span>
                    </template>

                    <!-- Status -->
                    <template #cell-status="{ row }">
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold font-tajawal"
                            :class="row.status === 'confirmed' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30'"
                        >
                            {{ row.status === 'confirmed' ? $t('invoices.status_confirmed') : $t('invoices.status_cancelled') }}
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center gap-1.5 font-tajawal">
                            <!-- View Details -->
                            <button
                                @click="openDetailsModal(row)"
                                type="button"
                                class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer border border-slate-200 dark:border-transparent"
                            >
                                {{ $t('common.details') }} ({{ row.items_count }})
                            </button>

                            <!-- Cancel -->
                            <button
                                v-if="row.status === 'confirmed'"
                                @click="cancelPurchase(row)"
                                type="button"
                                class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition cursor-pointer"
                                :title="$t('purchases.cancel_btn_title')"
                            >
                                ✕
                            </button>
                        </div>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs font-tajawal">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2.5">
                                <div class="space-y-0.5">
                                    <Link :href="`/purchases/${row.id}`" class="font-mono font-black text-sm text-theme-primary hover:underline">
                                        #{{ row.purchase_number }}
                                    </Link>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">{{ row.supplier_name }} <span v-if="row.company_name">({{ row.company_name }})</span></p>
                                </div>

                                <div class="text-left font-mono">
                                    <div class="font-black text-sm text-slate-900 dark:text-white">
                                        {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
                                    </div>
                                    <div v-if="row.remaining_amount > 0" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold">
                                        متبقي: {{ formatMoney(row.remaining_amount) }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>🏬 {{ row.store_name || '—' }} • {{ row.purchase_date }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="row.status === 'confirmed' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/15 text-rose-600 dark:text-rose-400'"
                                >
                                    {{ row.status === 'confirmed' ? $t('invoices.status_confirmed') : $t('invoices.status_cancelled') }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-200 dark:border-slate-800/80">
                                <button
                                    @click="openDetailsModal(row)"
                                    type="button"
                                    class="flex-1 h-10 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-xs border border-slate-200 dark:border-slate-700"
                                >
                                    <span>📋</span>
                                    <span>{{ $t('common.details') }} ({{ row.items_count }})</span>
                                </button>

                                <button
                                    v-if="row.status === 'confirmed'"
                                    @click="cancelPurchase(row)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs shrink-0"
                                    :title="$t('purchases.cancel_btn_title')"
                                >
                                    ✕
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
                    <label class="text-xs font-black text-slate-300">🔍 {{ $t('purchases.search_placeholder') }}</label>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('invoices.filter_by_search')"
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-950/80 border border-slate-800 text-xs text-white focus:border-amber-500 focus:outline-none transition"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-300">🏭 {{ $t('purchases.supplier') }}</label>
                    <SearchableSelect
                        v-model="supplierId"
                        :options="supplierOptions"
                        :placeholder="$t('purchases.select_supplier')"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-300">🟢 {{ $t('common.status') }}</label>
                    <SearchableSelect
                        v-model="status"
                        :options="statusOptions"
                        :placeholder="$t('common.status')"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-300">{{ $t('invoices.date_from') }}</label>
                        <DatePicker v-model="dateFrom" :placeholder="$t('invoices.date_from')" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-black text-slate-300">{{ $t('invoices.date_to') }}</label>
                        <DatePicker v-model="dateTo" :placeholder="$t('invoices.date_to')" />
                    </div>
                </div>
            </div>
        </FilterDrawer>

        <!-- Purchase Details Modal (Smooth Native Pop) -->
        <Teleport to="body">
            <Transition name="modal-zoom">
                <div
                    v-if="showDetailsModal && selectedPurchase"
                    @click="showDetailsModal = false"
                    class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
                    dir="rtl"
                >
                    <div @click.stop class="w-full max-w-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-black text-base text-slate-900 dark:text-white">{{ $t('purchases.details_title') }}: {{ selectedPurchase.purchase_number }}</h3>
                                <p class="text-xs text-amber-600 dark:text-amber-400 font-bold mt-0.5">{{ $t('purchases.supplier') }}: {{ selectedPurchase.supplier_name }} | {{ $t('common.date') }}: {{ selectedPurchase.purchase_date }}</p>
                            </div>
                            <button
                                @click="showDetailsModal = false"
                                class="w-9 h-9 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-400 text-xs hover:text-slate-900 dark:hover:text-white cursor-pointer flex items-center justify-center transition active:scale-90 shadow-xs"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Items list -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-right text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                        <th class="pb-2">{{ $t('purchases.supplied_item') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('common.quantity') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('invoices.unit_price') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('common.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 font-sans">
                                    <tr v-for="it in selectedPurchase.items" :key="it.id">
                                        <td class="py-2.5 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.item_name }}</td>
                                        <td class="py-2.5 font-mono font-bold text-amber-600 dark:text-amber-400">{{ it.quantity }}</td>
                                        <td class="py-2.5 font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(it.unit_cost) }}</td>
                                        <td class="py-2.5 font-mono font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(it.subtotal) }} {{ $t('common.currency') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 bg-slate-50 dark:bg-slate-950/80 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between font-mono">
                            <span class="text-xs text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('purchases.net_invoice_total') }}:</span>
                            <span class="text-lg font-black text-amber-600 dark:text-amber-400">{{ formatMoney(selectedPurchase.net_total) }} {{ $t('common.currency') }}</span>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>