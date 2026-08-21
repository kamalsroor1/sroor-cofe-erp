<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import FilterDrawer from '@/Components/FilterDrawer.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import ItemFormModal from '@/Components/Items/ItemFormModal.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';
import {
    Package,
    Plus,
    AlertTriangle,
    Search,
    Filter,
    Pencil,
    Trash2,
    History,
    X,
    Boxes,
    FolderTree
} from 'lucide-vue-next';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

// Search & Filter state
const search = ref(props.filters.search || '');
const category = ref(props.filters.category || 'all');
const stockStatus = ref(props.filters.stock_status || 'all');
const status = ref(props.filters.status || 'all');

const isDrawerOpen = ref(false);

const activeFiltersCount = computed(() => {
    let count = 0;
    if (search.value) count++;
    if (category.value && category.value !== 'all') count++;
    if (stockStatus.value && stockStatus.value !== 'all') count++;
    if (status.value && status.value !== 'all') count++;
    return count;
});

const categoryOptions = computed(() => [
    { id: 'all', name: trans('inventory.all_categories') || 'كافة التصنيفات والأقسام' },
    ...props.categories.map(c => ({ id: c, name: c }))
]);

const stockStatusOptions = computed(() => [
    { id: 'all', name: trans('inventory.all_stock') || 'كافة حالات المخزون' },
    { id: 'low', name: trans('inventory.low_stock_only') || 'الأصناف الحرجة والنواقص' },
    { id: 'out', name: trans('inventory.out_of_stock_only') || 'أصناف نفدت من المخزن (رصيد 0)' },
    { id: 'in_stock', name: trans('inventory.available_only') || 'أصناف متوفرة بالمخزن' },
]);

const statusOptions = computed(() => [
    { id: 'all', name: trans('common.all') || 'الكل' },
    { id: 'active', name: trans('common.active') || 'الأصناف النشطة' },
    { id: 'inactive', name: trans('common.inactive') || 'الأصناف المعطلة' },
]);

const applyFilters = () => {
    router.get('/items', {
        search: search.value || undefined,
        category: category.value !== 'all' ? category.value : undefined,
        stock_status: stockStatus.value !== 'all' ? stockStatus.value : undefined,
        status: status.value !== 'all' ? status.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => { isDrawerOpen.value = false; }
    });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
});

const resetFilters = () => {
    search.value = '';
    category.value = 'all';
    stockStatus.value = 'all';
    status.value = 'all';
    applyFilters();
};

// ─── Modal State ──────────────────────────────────────────────────────────────
const showItemModal = ref(false);
const editingItem = ref(null);

const itemForm = useForm({
    name: '',
    code: '',
    category: '',
    unit: 'كجم',
    cost_price: '',
    selling_price: '',
    min_stock_level: '5.000',
    notes: '',
});

const openCreateModal = () => {
    editingItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
    showItemModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.name = item.name;
    itemForm.code = item.code || '';
    itemForm.category = item.category || '';
    itemForm.unit = item.unit || 'كجم';
    itemForm.cost_price = item.cost_price;
    itemForm.selling_price = item.selling_price;
    itemForm.min_stock_level = item.min_stock_level;
    itemForm.notes = item.notes || '';
    showItemModal.value = true;
};

const saveItem = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => { showItemModal.value = false; }
    };
    editingItem.value
        ? itemForm.put(`/items/${editingItem.value.id}`, options)
        : itemForm.post('/items', options);
};

const deleteItem = (item) => {
    if (!item.can_be_deleted) {
        alert('لا يمكن حذف الصنف:\n- ' + item.deletion_blockers.join('\n- '));
        return;
    }
    if (confirm(`هل أنت متأكد من حذف الصنف (${item.name})؟`)) {
        router.delete(`/items/${item.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="$t('inventory.title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('inventory.items_title')"
                :subtitle="$t('inventory.items_subtitle')"
                :icon="Package"
            >
                <template #actions>
                    <Link
                        href="/items/movements"
                        class="h-11 px-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-95 shadow-xs"
                    >
                        <History class="w-4 h-4 text-theme-primary" />
                        <span>{{ $t('inventory.stock_card_btn') }}</span>
                    </Link>

                    <button
                        type="button"
                        class="h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-sm"
                        @click="openCreateModal"
                    >
                        <Plus class="w-4 h-4" />
                        <span>{{ $t('inventory.add_new_item') }}</span>
                    </button>
                </template>
            </PageHeader>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4">
                <MetricCard
                    :title="$t('inventory.total_items_count')"
                    :value="metrics.total_items || 0"
                    :currency="$t('inventory.item_unit')"
                    :icon="Package"
                />
                <MetricCard
                    :title="$t('inventory.low_stock_count')"
                    :value="metrics.low_stock_count || 0"
                    :currency="$t('inventory.item_unit')"
                    variant="danger"
                    :icon="AlertTriangle"
                    :subtitle="metrics.low_stock_count > 0 ? $t('inventory.low_stock_warning') : ''"
                />
                <MetricCard
                    class="col-span-2 sm:col-span-1"
                    :title="$t('inventory.total_inventory_value')"
                    :value="formatMoney(metrics.total_stock_value)"
                    :currency="$t('common.currency')"
                    variant="success"
                    :icon="Boxes"
                />
            </div>

            <!-- Filter & Search Quick Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="w-full md:w-96 relative">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('inventory.search_item_placeholder')"
                            class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-theme-primary focus:outline-none transition shadow-inner font-tajawal"
                        >
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 pointer-events-none">
                            <Search class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="w-full md:w-auto flex flex-wrap items-center justify-between md:justify-end gap-2">
                        <!-- Quick Stock Tabs -->
                        <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950/80 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
                            <button
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer flex items-center justify-center active:scale-95"
                                :class="stockStatus === 'all' ? 'tab-theme-active shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                @click="stockStatus = 'all'; applyFilters();"
                            >
                                {{ $t('common.all') }}
                            </button>
                            <button
                                type="button"
                                class="h-9 px-3 rounded-xl font-bold transition cursor-pointer flex items-center justify-center active:scale-95"
                                :class="stockStatus === 'low' ? 'bg-rose-500 text-white font-black shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                @click="stockStatus = 'low'; applyFilters();"
                            >
                                {{ $t('inventory.low_stock_only') }}
                            </button>
                        </div>

                        <!-- Filter Drawer Button -->
                        <button
                            type="button"
                            class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 text-xs font-bold flex items-center gap-2 transition cursor-pointer shadow-xs active:scale-95"
                            @click="isDrawerOpen = true"
                        >
                            <Filter class="w-4 h-4" />
                            <span>{{ $t('common.filter') }}</span>
                            <span
                                v-if="activeFiltersCount > 0"
                                class="w-5 h-5 rounded-full bg-theme-primary text-white font-mono font-black text-[11px] flex items-center justify-center"
                            >
                                {{ activeFiltersCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Active Filters Chips -->
                <div v-if="activeFiltersCount > 0" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-800/80 text-xs">
                    <span class="text-slate-500 text-[11px] font-bold">{{ $t('dashboard.quick_filter') || 'الفلاتر النشطة' }}:</span>

                    <span v-if="category !== 'all'" class="px-2.5 py-1 rounded-xl bg-theme-light border border-theme-light text-theme-primary flex items-center gap-1.5 font-bold">
                        <span>{{ $t('inventory.category') }}: {{ category }}</span>
                        <button class="hover:text-rose-400 cursor-pointer" @click="category = 'all'; applyFilters();">
                            <X class="w-3 h-3" />
                        </button>
                    </span>

                    <button
                        class="text-slate-500 hover:text-rose-500 dark:text-slate-400 dark:hover:text-rose-400 text-xs underline font-bold mr-1 cursor-pointer"
                        @click="resetFilters"
                    >
                        {{ $t('common.clear_all') || 'مسح كافة الفلاتر' }}
                    </button>
                </div>
            </div>

            <!-- Items Table & Mobile Cards -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-4 overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                <th class="pb-3">{{ $t('inventory.item_code') }}</th>
                                <th class="pb-3">{{ $t('inventory.item_name') }}</th>
                                <th class="pb-3">{{ $t('inventory.category') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.current_stock') }}</th>
                                <th class="pb-3 font-mono">{{ $t('common.unit_cost') }}</th>
                                <th class="pb-3 font-mono">{{ $t('common.unit_price') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.min_stock_level') }}</th>
                                <th class="pb-3 text-center">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                            <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-3.5 font-mono text-slate-500 dark:text-slate-400 text-[11px]">{{ item.code || '—' }}</td>

                                <td class="py-3.5">
                                    <div class="font-black text-slate-900 dark:text-white font-tajawal flex items-center gap-1.5">
                                        <span>{{ item.name }}</span>
                                        <span v-if="item.is_low_stock" class="px-1.5 rounded bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold">
                                            {{ $t('inventory.low_stock_only') }}
                                        </span>
                                    </div>
                                    <div v-if="item.notes" class="text-[10px] text-slate-500 truncate max-w-xs font-tajawal">{{ item.notes }}</div>
                                </td>

                                <td class="py-3.5 font-tajawal">
                                    <span v-if="item.category" class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[11px]">
                                        {{ item.category }}
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-slate-600">—</span>
                                </td>

                                <td class="py-3.5 font-mono font-bold">
                                    <span
                                        class="px-2.5 py-1 rounded-xl border font-black text-xs"
                                        :class="item.is_low_stock ? 'bg-rose-500/15 border-rose-500/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
                                    >
                                        {{ item.current_stock }} {{ item.unit }}
                                    </span>
                                </td>

                                <td class="py-3.5 font-mono text-slate-500 dark:text-slate-400">{{ formatMoney(item.cost_price) }}</td>
                                <td class="py-3.5 font-mono font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ formatMoney(item.selling_price) }}</td>
                                <td class="py-3.5 font-mono text-slate-500 dark:text-slate-400">{{ item.min_stock_level }} {{ item.unit }}</td>

                                <td class="py-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <Link
                                            :href="`/items/${item.id}/movements`"
                                            class="p-1.5 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 border border-indigo-500/30 text-indigo-600 dark:text-indigo-400 transition"
                                            :title="$t('inventory.view_movements')"
                                        >
                                            <History class="w-3.5 h-3.5" />
                                        </Link>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-amber-600 dark:text-amber-400 transition cursor-pointer"
                                            :title="$t('common.edit')"
                                            @click="openEditModal(item)"
                                        >
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 transition cursor-pointer"
                                            :class="!item.can_be_deleted ? 'opacity-40 cursor-not-allowed' : ''"
                                            :title="item.can_be_deleted ? $t('common.delete') : item.deletion_blockers.join(', ')"
                                            @click="deleteItem(item)"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards View -->
                <div class="md:hidden space-y-3">
                    <div
                        v-for="item in items.data"
                        :key="item.id"
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs font-tajawal"
                    >
                        <!-- Top: Name + Category -->
                        <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2.5">
                            <div class="space-y-1">
                                <div class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span>{{ item.name }}</span>
                                    <span v-if="item.is_low_stock" class="px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold">
                                        {{ $t('inventory.low_stock_only') }}
                                    </span>
                                </div>
                                <p v-if="item.code" class="text-[11px] text-slate-400 font-mono" dir="ltr">#{{ item.code }}</p>
                            </div>

                            <span v-if="item.category" class="px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[11px]">
                                {{ item.category }}
                            </span>
                        </div>

                        <!-- Metrics Mini Grid -->
                        <div class="grid grid-cols-3 gap-2 p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                            <div>
                                <span class="text-[10px] text-slate-400 font-tajawal block">{{ $t('inventory.current_stock') }}</span>
                                <span class="text-xs font-black" :class="item.is_low_stock ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                                    {{ item.current_stock }} {{ item.unit }}
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-tajawal block">{{ $t('common.unit_cost') }}</span>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ formatMoney(item.cost_price) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-tajawal block">{{ $t('common.unit_price') }}</span>
                                <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(item.selling_price) }}</span>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[11px] text-slate-400 font-mono">
                                {{ $t('inventory.min_stock_level') }}: {{ item.min_stock_level }} {{ item.unit }}
                            </span>

                            <div class="flex items-center gap-2">
                                <Link
                                    :href="`/items/${item.id}/movements`"
                                    class="w-10 h-10 rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs"
                                    :title="$t('inventory.view_movements')"
                                >
                                    <History class="w-4 h-4" />
                                </Link>

                                <button
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs"
                                    :title="$t('common.edit')"
                                    @click="openEditModal(item)"
                                >
                                    <Pencil class="w-4 h-4" />
                                </button>

                                <button
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 flex items-center justify-center transition active:scale-90 cursor-pointer shadow-xs"
                                    :class="!item.can_be_deleted ? 'opacity-40 cursor-not-allowed' : ''"
                                    :title="item.can_be_deleted ? $t('common.delete') : item.deletion_blockers.join(', ')"
                                    @click="deleteItem(item)"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <EmptyState
                    v-if="!items.data || items.data.length === 0"
                    :icon="Package"
                    :title="$t('inventory.no_items_found')"
                    :action-label="$t('inventory.add_new_item')"
                    :action-icon="Plus"
                    @action="openCreateModal"
                />

                <!-- Pagination -->
                <Pagination
                    :links="items.links"
                    :from="items.from"
                    :to="items.to"
                    :total="items.total"
                />
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
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <Search class="w-3.5 h-3.5" />
                        <span>{{ $t('inventory.search_item_placeholder') }}</span>
                    </label>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('common.search')"
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-xs text-slate-900 dark:text-white focus:border-amber-500 focus:outline-none transition"
                    >
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <FolderTree class="w-3.5 h-3.5" />
                        <span>{{ $t('inventory.category') }}</span>
                    </label>
                    <SearchableSelect
                        v-model="category"
                        :options="categoryOptions"
                        :placeholder="$t('inventory.all_categories')"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <AlertTriangle class="w-3.5 h-3.5" />
                        <span>{{ $t('inventory.stock_status') }}</span>
                    </label>
                    <SearchableSelect
                        v-model="stockStatus"
                        :options="stockStatusOptions"
                        :placeholder="$t('inventory.all_stock')"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <Filter class="w-3.5 h-3.5" />
                        <span>{{ $t('common.status') }}</span>
                    </label>
                    <SearchableSelect
                        v-model="status"
                        :options="statusOptions"
                        :placeholder="$t('common.all')"
                    />
                </div>
            </div>
        </FilterDrawer>

        <!-- Add / Edit Item Modal -->
        <ItemFormModal
            :show="showItemModal"
            :editing-item="editingItem"
            :form="itemForm"
            @close="showItemModal = false"
            @submit="saveItem"
        />
    </AppLayout>
</template>
