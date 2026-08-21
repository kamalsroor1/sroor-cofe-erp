<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useMoney } from '@/Composables/useMoney';
import { trans } from '@/helpers/trans';

const props = defineProps({
    tab: { type: String, default: 'items' },
    records: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const trashColumns = computed(() => [
    { key: 'name', label: trans('trash.record_name_col') },
    { key: 'deleted_at', label: trans('trash.deleted_at_col'), mono: true },
    { key: 'additional_info', label: trans('trash.additional_info_col') },
    { key: 'actions', label: trans('common.actions'), align: 'center' },
]);

const { formatMoney } = useMoney();

const currentTab = ref(props.tab);
const search = ref(props.filters.search || '');

const tabs = computed(() => [
    { id: 'items', name: trans('trash.tab_items') || 'الأصناف والمخزون 📦', countKey: 'items' },
    { id: 'customers', name: trans('trash.tab_customers') || 'العملاء 👥', countKey: 'customers' },
    { id: 'suppliers', name: trans('trash.tab_suppliers') || 'الموردين 🏭', countKey: 'suppliers' },
    { id: 'stores', name: trans('trash.tab_stores') || 'الفروع والمخازن 🏬', countKey: 'stores' },
    { id: 'expenses', name: trans('trash.tab_expenses') || 'المصروفات 💸', countKey: 'expenses' },
    { id: 'returns', name: trans('trash.tab_returns') || 'المرتجعات 🔄', countKey: 'returns' },
]);

const setTab = (t) => {
    currentTab.value = t;
    search.value = '';
    applyFilters();
};

const applyFilters = () => {
    router.get('/trash', {
        tab: currentTab.value,
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters();
    }, 400);
});

const restoreRecord = (id) => {
    router.post(`/trash/${currentTab.value}/${id}/restore`, {}, {
        preserveScroll: true,
    });
};

const forceDeleteRecord = (id) => {
    const confirmMsg = trans('trash.confirm_force_delete') || 'تحذير: الحذف النهائي لا يمكن التراجع عنه أبداً. هل أنت متأكد؟';
    if (confirm(confirmMsg)) {
        router.delete(`/trash/${currentTab.value}/${id}/force-delete`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="$t('trash.title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('trash.title')"
                :subtitle="$t('trash.subtitle')"
                icon="🗑️"
            />

            <!-- Tabs Navigation -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    @click="setTab(t.id)"
                    type="button"
                    class="px-4 py-2 rounded-2xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
                    :class="currentTab === t.id ? 'tab-theme-active shadow-xs' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                >
                    <span>{{ t.name }}</span>
                    <span
                        v-if="counts[t.countKey] > 0"
                        class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white"
                    >
                        {{ counts[t.countKey] }}
                    </span>
                </button>
            </div>

            <!-- Table & List View -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 font-tajawal">
                <!-- Search -->
                <div class="w-full md:w-80 relative">
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('trash.search_trash')"
                        class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-theme-primary focus:outline-none transition"
                    >
                    <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 text-xs pointer-events-none">
                        🔍
                    </span>
                </div>

                <!-- DataTable Content -->
                <DataTable
                    :columns="trashColumns"
                    :rows="records.data"
                    :pagination="records"
                    :empty-title="$t('trash.empty_trash')"
                    empty-icon="🎉"
                >
                    <!-- Record Name -->
                    <template #cell-name="{ row }">
                        <span class="font-black text-slate-900 dark:text-white font-tajawal">
                            {{ row.name || row.title || row.invoice_number || row.transfer_number || `#${row.id}` }}
                        </span>
                    </template>

                    <!-- Deleted At -->
                    <template #cell-deleted_at="{ row }">
                        <span class="font-mono text-rose-500 text-[11px]">
                            {{ row.deleted_at }}
                        </span>
                    </template>

                    <!-- Additional Info -->
                    <template #cell-additional_info="{ row }">
                        <span class="text-slate-500 dark:text-slate-400 font-tajawal">
                            <span v-if="row.sku" class="font-mono text-xs">SKU: {{ row.sku }}</span>
                            <span v-else-if="row.phone" class="font-mono text-xs">{{ row.phone }}</span>
                            <span v-else-if="row.amount" class="font-mono text-xs font-bold">{{ formatMoney(row.amount) }} {{ $t('common.currency') }}</span>
                            <span v-else>—</span>
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center gap-2 font-tajawal">
                            <button
                                @click="restoreRecord(row.id)"
                                type="button"
                                class="px-3 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold transition cursor-pointer text-xs"
                            >
                                {{ $t('trash.restore_btn') }}
                            </button>

                            <button
                                @click="forceDeleteRecord(row.id)"
                                type="button"
                                class="px-3 py-1.5 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold transition cursor-pointer text-xs"
                            >
                                {{ $t('trash.force_delete_btn') }}
                            </button>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>