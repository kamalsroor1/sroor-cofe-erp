<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { trans } from '@/helpers/trans';

const props = defineProps({
    transfers: { type: Object, required: true },
    stores: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const transferColumns = [
    { key: 'transfer_number', label: trans('inventory.transfer_number') || 'رقم التحويل', sortable: true, mono: true },
    { key: 'from_store_name', label: trans('inventory.from_store') || 'من مخزن' },
    { key: 'to_store_name', label: trans('inventory.to_store') || 'إلى مخزن' },
    { key: 'transfer_date', label: trans('common.date'), mono: true },
    { key: 'items_count', label: trans('inventory.transferred_items_count') || 'الأصناف', align: 'center' },
    { key: 'status', label: trans('common.status'), align: 'center' },
    { key: 'actions', label: trans('common.actions'), align: 'center' },
];

const search = ref(props.filters.search || '');
const fromStoreId = ref(props.filters.from_store_id || 'all');
const toStoreId = ref(props.filters.to_store_id || 'all');

const applyFilters = () => {
    router.get('/stock-transfers', {
        search: search.value || undefined,
        from_store_id: fromStoreId.value !== 'all' ? fromStoreId.value : undefined,
        to_store_id: toStoreId.value !== 'all' ? toStoreId.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let searchTimer = null;
watch([search, fromStoreId, toStoreId], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters();
    }, 400);
});

// Transfer Details Modal
const showDetailsModal = ref(false);
const selectedTransfer = ref(null);

const openDetailsModal = (t) => {
    selectedTransfer.value = t;
    showDetailsModal.value = true;
};
</script>

<template>
    <Head :title="$t('inventory.transfers_title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('inventory.transfers_title')"
                :subtitle="$t('inventory.transfers_subtitle')"
                icon="🚚"
            >
                <template #actions>
                    <Link
                        href="/stock-transfers/create"
                        class="h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-sm"
                    >
                        <span class="text-base font-black">+</span>
                        <span>{{ $t('inventory.new_transfer') }}</span>
                    </Link>
                </template>
            </PageHeader>

            <!-- Transfers Data Table -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <DataTable
                    :columns="transferColumns"
                    :rows="transfers.data"
                    :pagination="transfers"
                    :empty-title="$t('inventory.no_transfers_found')"
                    empty-icon="🚚"
                >
                    <!-- Number -->
                    <template #cell-transfer_number="{ row }">
                        <span class="font-mono font-black text-theme-primary">
                            {{ row.transfer_number }}
                        </span>
                    </template>

                    <!-- From Store -->
                    <template #cell-from_store_name="{ row }">
                        <span class="font-bold text-rose-600 dark:text-rose-400 font-tajawal">
                            {{ row.from_store_name }}
                        </span>
                    </template>

                    <!-- To Store -->
                    <template #cell-to_store_name="{ row }">
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 font-tajawal">
                            {{ row.to_store_name }}
                        </span>
                    </template>

                    <!-- Date -->
                    <template #cell-transfer_date="{ row }">
                        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                            {{ row.transfer_date }}
                        </span>
                    </template>

                    <!-- Items Count -->
                    <template #cell-items_count="{ row }">
                        <span class="text-slate-700 dark:text-slate-300 font-tajawal font-bold">
                            {{ row.items_count }} {{ $t('inventory.item_unit') }}
                        </span>
                    </template>

                    <!-- Status -->
                    <template #cell-status="{ row }">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                            {{ $t('common.success') }} 🟢
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #cell-actions="{ row }">
                        <button
                            @click="openDetailsModal(row)"
                            type="button"
                            class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
                        >
                            {{ $t('inventory.view_items') }}
                        </button>
                    </template>

                    <!-- Mobile Card Custom Slot -->
                    <template #mobile-card="{ row }">
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs font-tajawal">
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800/80 pb-2.5">
                                <div>
                                    <div class="font-mono font-black text-sm text-theme-primary">{{ row.transfer_number }}</div>
                                    <p class="text-[11px] text-slate-400 font-mono">{{ row.transfer_date }}</p>
                                </div>

                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                    {{ $t('common.success') }} 🟢
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs font-bold bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                                <span class="text-rose-600 dark:text-rose-400">من: {{ row.from_store_name }}</span>
                                <span class="text-slate-400">←</span>
                                <span class="text-emerald-600 dark:text-emerald-400">إلى: {{ row.to_store_name }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">
                                    📦 {{ row.items_count }} {{ $t('inventory.item_unit') }}
                                </span>

                                <button
                                    @click="openDetailsModal(row)"
                                    type="button"
                                    class="h-10 px-4 rounded-xl bg-theme-light text-theme-primary font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer shadow-xs border border-theme-light"
                                >
                                    <span>📋</span>
                                    <span>{{ $t('inventory.view_items') }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Transfer Details Modal (Smooth Native Pop) -->
        <Teleport to="body">
            <Transition name="modal-zoom">
                <div
                    v-if="showDetailsModal && selectedTransfer"
                    @click="showDetailsModal = false"
                    class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 font-tajawal select-none"
                    dir="rtl"
                >
                    <div @click.stop class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl space-y-4 text-slate-900 dark:text-white max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                            <div>
                                <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white">{{ $t('inventory.transfers_title') }}: {{ selectedTransfer.transfer_number }}</h3>
                                <p class="text-xs text-theme-primary font-bold mt-0.5">{{ selectedTransfer.from_store_name }} ← {{ selectedTransfer.to_store_name }}</p>
                            </div>
                            <button
                                @click="showDetailsModal = false"
                                type="button"
                                class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center justify-center text-sm font-bold transition active:scale-90 cursor-pointer shadow-xs"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-right text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                        <th class="pb-2">{{ $t('inventory.item_name') }}</th>
                                        <th class="pb-2 font-mono">{{ $t('inventory.transferred_quantity') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                                    <tr v-for="it in selectedTransfer.items" :key="it.id">
                                        <td class="py-2.5 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.item_name }}</td>
                                        <td class="py-2.5 font-mono font-black text-theme-primary">{{ it.quantity }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>