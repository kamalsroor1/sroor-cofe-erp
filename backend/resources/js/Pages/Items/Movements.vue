<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import StatCardSkeleton from '@/Components/Common/Skeletons/StatCardSkeleton.vue';
import TableSkeleton from '@/Components/Common/Skeletons/TableSkeleton.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    item: { type: Object, required: true },
    movements: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => null },
    stores: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const movementColumns = computed(() => [
    { key: 'created_at', label: `${trans('common.date')} & ${trans('common.time')}`, mono: true },
    { key: 'movement_type', label: trans('inventory.movement_type') },
    { key: 'document_number', label: trans('contacts.reference_no'), mono: true },
    { key: 'quantity', label: trans('common.quantity'), mono: true },
    { key: 'stock_before', label: `${trans('inventory.current_stock')} (${trans('inventory.balance_before') || 'قبل'})`, mono: true },
    { key: 'stock_after', label: trans('inventory.balance_after'), mono: true },
    { key: 'store_and_user', label: `${trans('common.store')} / ${trans('common.user')}` },
]);

const { formatMoney } = useMoney();

const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const storeId = ref(props.filters.store_id || 'all');
const movementType = ref(props.filters.type || 'all');

const storeOptions = computed(() => [
    { id: 'all', name: `🏬 ${trans('common.all') || 'كافة الفروع والمخازن'}` },
    ...props.stores.map(s => ({ id: s.id, name: `🏬 ${s.name}` }))
]);

const movementTypeOptions = computed(() => [
    { id: 'all', name: trans('inventory.all_stock') || 'كافة أنواع الحركات' },
    { id: 'purchase_in', name: `🚛 ${trans('inventory.movement_purchase') || 'توريد مشتريات'}` },
    { id: 'sales_out', name: `🛒 ${trans('inventory.movement_sale') || 'مبيعات وفواتير POS'}` },
    { id: 'transfer_in', name: `📥 ${trans('inventory.movement_transfer_in') || 'تحويل وارد من مخزن'}` },
    { id: 'transfer_out', name: `📤 ${trans('inventory.movement_transfer_out') || 'تحويل منصرف إلى مخزن'}` },
    { id: 'sales_return_in', name: `↩️ ${trans('inventory.movement_sale_return') || 'مرتجع مبيعات من عميل'}` },
    { id: 'purchase_return_out', name: `↪️ ${trans('inventory.movement_purchase_return') || 'مرتجع مشتريات إلى مورد'}` },
    { id: 'stock_adjustment_in', name: `⚖️ ${trans('inventory.movement_adjustment') || 'تسوية جردية (إضافة)'}` },
    { id: 'stock_adjustment_out', name: `⚖️ ${trans('inventory.movement_adjustment') || 'تسوية جردية (خصم)'}` },
    { id: 'cancellation_in', name: `🚫 ${trans('invoices.cancelled') || 'إلغاء فاتورة مبيعات'}` },
    { id: 'waste_out', name: '🗑️ هالك وتالف' },
]);

const applyDatePreset = (preset) => {
    const now = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        dateFrom.value = formatDate(now);
        dateTo.value = formatDate(now);
    } else if (preset === 'this_week') {
        const firstDay = new Date(now.setDate(now.getDate() - now.getDay()));
        const lastDay = new Date(now.setDate(now.getDate() - now.getDay() + 6));
        dateFrom.value = formatDate(firstDay);
        dateTo.value = formatDate(lastDay);
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
    applyFilters();
};

const applyFilters = () => {
    router.get(`/items/${props.item.id}/movements`, {
        from: dateFrom.value || undefined,
        to: dateTo.value || undefined,
        store_id: storeId.value !== 'all' ? storeId.value : undefined,
        type: movementType.value !== 'all' ? movementType.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const printReport = () => {
    window.print();
};

const getMovementBadge = (type) => {
    const map = {
        'purchase_in': { label: trans('inventory.movement_purchase') || 'توريد مشتريات', class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
        'sales_out': { label: trans('inventory.movement_sale') || 'مبيعات وفواتير', class: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30' },
        'transfer_in': { label: trans('inventory.movement_transfer_in') || 'تحويل وارد', class: 'bg-teal-500/15 text-teal-400 border-teal-500/30' },
        'transfer_out': { label: trans('inventory.movement_transfer_out') || 'تحويل منصرف', class: 'bg-amber-500/15 text-amber-400 border-amber-500/30' },
        'sales_return_in': { label: trans('inventory.movement_sale_return') || 'مرتجع مبيعات', class: 'bg-blue-500/15 text-blue-400 border-blue-500/30' },
        'purchase_return_out': { label: trans('inventory.movement_purchase_return') || 'مرتجع مشتريات', class: 'bg-purple-500/15 text-purple-400 border-purple-500/30' },
        'stock_adjustment_in': { label: trans('inventory.movement_adjustment') || 'تسوية (إضافة)', class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
        'stock_adjustment_out': { label: trans('inventory.movement_adjustment') || 'تسوية (خصم)', class: 'bg-rose-500/15 text-rose-400 border-rose-500/30' },
        'cancellation_in': { label: trans('invoices.cancelled') || 'إلغاء فاتورة', class: 'bg-rose-500/15 text-rose-400 border-rose-500/30' },
        'waste_out': { label: 'هالك وتالف', class: 'bg-rose-500/15 text-rose-400 border-rose-500/30' },
    };
    return map[type] || { label: type, class: 'bg-slate-800 text-slate-300 border-slate-700' };
};
</script>

<template>
    <Head :title="`${$t('inventory.movements_title')}: ${item.name}`" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <Link href="/items" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-sm transition active:scale-90 shadow-xs border border-slate-200 dark:border-transparent">
                            →
                        </Link>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>{{ $t('inventory.movements_title') }}:</span>
                                <span class="text-theme-primary">{{ item.name }}</span>
                                <span v-if="item.code" class="text-xs text-slate-500 dark:text-slate-400 font-mono font-normal">({{ item.code }})</span>
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-bold mt-0.5">
                                {{ $t('inventory.category') }}: {{ item.category || '—' }} | {{ $t('inventory.unit') }}: {{ item.unit }} | {{ $t('inventory.purchase_price') }}: {{ formatMoney(item.cost_price) }} {{ $t('common.currency') }} | {{ $t('inventory.retail_price') }}: {{ formatMoney(item.selling_price) }} {{ $t('common.currency') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        @click="printReport"
                        type="button"
                        class="w-full sm:w-auto h-11 px-5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/20 transition active:scale-95 cursor-pointer"
                    >
                        <span>📄</span>
                        <span>{{ $t('contacts.print_statement') }} A4</span>
                    </button>
                </div>
            </div>

            <!-- 4 Top KPI Cards (Bento Grid on Mobile, Deferred with Skeleton) -->
            <Deferred data="stats">
                <template #fallback>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5 font-tajawal">
                        <StatCardSkeleton v-for="i in 4" :key="i" />
                    </div>
                </template>

                <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5 font-tajawal animate-in fade-in duration-500">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold block">{{ $t('inventory.quantity_in') }}</span>
                        <div class="text-lg sm:text-xl font-black font-mono text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>📥</span>
                            <span>{{ stats.total_in }}</span>
                            <span class="text-xs font-tajawal text-slate-500 dark:text-slate-400 font-normal">{{ item.unit }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold block">{{ $t('inventory.quantity_out') }}</span>
                        <div class="text-lg sm:text-xl font-black font-mono text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                            <span>📤</span>
                            <span>{{ stats.total_out }}</span>
                            <span class="text-xs font-tajawal text-slate-500 dark:text-slate-400 font-normal">{{ item.unit }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold block">{{ $t('contacts.period_net') || 'صافي الحركة للفترة' }}</span>
                        <div class="text-lg sm:text-xl font-black font-mono flex items-center gap-1.5" :class="stats.net_movement >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                            <span>⚖️</span>
                            <span>{{ stats.net_movement >= 0 ? '+' : '' }}{{ stats.net_movement }}</span>
                            <span class="text-xs font-tajawal text-slate-500 dark:text-slate-400 font-normal">{{ item.unit }}</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 space-y-1 shadow-xs">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold block">{{ $t('inventory.current_stock') }}</span>
                        <div class="text-lg sm:text-xl font-black font-mono text-theme-primary flex items-center gap-1.5">
                            <span>📦</span>
                            <span>{{ stats.current_scope_stock }}</span>
                            <span class="text-xs font-tajawal text-slate-500 dark:text-slate-400 font-normal">{{ item.unit }}</span>
                        </div>
                    </div>
                </div>
            </Deferred>

            <!-- Filter Controls & Presets -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-3 font-tajawal">
                <!-- Date Presets Bar -->
                <div class="flex flex-wrap items-center gap-1.5 pb-2 border-b border-slate-200 dark:border-slate-800/80 text-xs">
                    <span class="text-slate-500 dark:text-slate-400 font-bold text-[11px] ml-1">{{ $t('contacts.report_period') }}:</span>
                    <button @click="applyDatePreset('today')" type="button" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95">{{ $t('dashboard.today') || 'اليوم' }}</button>
                    <button @click="applyDatePreset('this_week')" type="button" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95">{{ $t('dashboard.this_week') || 'هذا الأسبوع' }}</button>
                    <button @click="applyDatePreset('this_month')" type="button" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95">{{ $t('dashboard.this_month') || 'هذا الشهر' }}</button>
                    <button @click="applyDatePreset('this_year')" type="button" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95">{{ $t('dashboard.this_year') || 'هذا العام' }}</button>
                    <button @click="applyDatePreset('all')" type="button" class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition active:scale-95">{{ $t('common.all') }}</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('inventory.movement_type') }}</label>
                        <SearchableSelect
                            v-model="movementType"
                            :options="movementTypeOptions"
                            :placeholder="$t('inventory.movement_type')"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('common.store') }}</label>
                        <SearchableSelect
                            v-model="storeId"
                            :options="storeOptions"
                            :placeholder="$t('common.store')"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('contacts.from_date') }}</label>
                        <DatePicker v-model="dateFrom" :placeholder="$t('contacts.from_date')" />
                    </div>

                    <div class="space-y-1 flex items-end gap-2">
                        <div class="flex-1">
                            <label class="text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $t('contacts.to_date') }}</label>
                            <DatePicker v-model="dateTo" :placeholder="$t('contacts.to_date')" />
                        </div>
                        <button
                            @click="applyFilters"
                            type="button"
                            class="h-11 px-5 rounded-2xl btn-primary-theme text-xs font-black transition active:scale-95 cursor-pointer shadow-theme-primary"
                        >
                            {{ $t('common.filter') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Movements Data Table (Deferred with TableSkeleton) -->
            <Deferred data="movements">
                <template #fallback>
                    <TableSkeleton :columns-count="7" :rows-count="6" />
                </template>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal animate-in fade-in duration-500">
                    <DataTable
                        :columns="movementColumns"
                        :rows="movements.data"
                        :pagination="movements"
                        :empty-title="$t('inventory.no_movements_found')"
                        empty-icon="📦"
                    >
                    <!-- Date & Time -->
                    <template #cell-created_at="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.created_at }}
                        </span>
                    </template>

                    <!-- Movement Type -->
                    <template #cell-movement_type="{ row }">
                        <span
                            class="px-2.5 py-1 rounded-xl text-[10px] font-black border font-tajawal"
                            :class="getMovementBadge(row.movement_type).class"
                        >
                            {{ getMovementBadge(row.movement_type).label }}
                        </span>
                    </template>

                    <!-- Document Number -->
                    <template #cell-document_number="{ row }">
                        <span class="font-mono text-theme-primary font-bold">
                            {{ row.document_number || '—' }}
                        </span>
                    </template>

                    <!-- Quantity -->
                    <template #cell-quantity="{ row }">
                        <span class="font-mono font-black text-sm" :class="row.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                            {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }} {{ item.unit }}
                        </span>
                    </template>

                    <!-- Stock Before -->
                    <template #cell-stock_before="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400">
                            {{ row.stock_before }}
                        </span>
                    </template>

                    <!-- Stock After -->
                    <template #cell-stock_after="{ row }">
                        <span class="font-mono font-bold text-slate-900 dark:text-white">
                            {{ row.stock_after }}
                        </span>
                    </template>

                    <!-- Store & User -->
                    <template #cell-store_and_user="{ row }">
                        <div class="font-tajawal text-slate-700 dark:text-slate-300">
                            <div>{{ row.store_name || $t('inventory.store_type_main') }}</div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ row.user_name }}</div>
                        </div>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs font-tajawal">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-black border"
                                        :class="getMovementBadge(row.movement_type).class"
                                    >
                                        {{ getMovementBadge(row.movement_type).label }}
                                    </span>
                                    <span v-if="row.document_number" class="font-mono text-xs font-bold text-theme-primary">#{{ row.document_number }}</span>
                                </div>
                                <span class="font-mono text-[11px] text-slate-400">{{ row.created_at }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs font-mono py-1">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('common.quantity') }}</span>
                                    <span class="font-black" :class="row.quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                        {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('inventory.balance_before') || 'الرصيد قبل' }}</span>
                                    <span class="font-bold text-slate-600 dark:text-slate-400">{{ row.stock_before }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('inventory.balance_after') || 'الرصيد بعد' }}</span>
                                    <span class="font-black text-slate-900 dark:text-white">{{ row.stock_after }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 font-tajawal border-t border-slate-200 dark:border-slate-800/80 pt-1.5">
                                <span>🏬 {{ row.store_name || $t('inventory.store_type_main') }}</span>
                                <span>👤 {{ row.user_name || '—' }}</span>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
            </Deferred>
        </div>
    </AppLayout>
</template>