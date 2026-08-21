<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import MetricCard from '@/Components/Common/MetricCard.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import { useMoney } from '@/Composables/useMoney';

const props = defineProps({
    stores: { type: Array, default: () => [] },
    selected_store_id: { type: [Number, String], required: true },
    stocks: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { formatMoney } = useMoney();

const currentStoreId = ref(props.selected_store_id);
const search = ref(props.filters.search || '');
const stockStatus = ref(props.filters.stock_status || 'all');

const applyFilters = () => {
    router.get('/store-stocks', {
        store_id: currentStoreId.value,
        search: search.value || undefined,
        stock_status: stockStatus.value !== 'all' ? stockStatus.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const switchStore = (storeId) => {
    currentStoreId.value = storeId;
    applyFilters();
};

const totalValuation = computed(() => {
    if (!props.stocks.data) return 0;
    return props.stocks.data.reduce((sum, item) => sum + (item.total_valuation || 0), 0);
});

const lowStockCount = computed(() => {
    if (!props.stocks.data) return 0;
    return props.stocks.data.filter(s => s.quantity <= s.min_stock_level).length;
});
</script>

<template>
    <Head :title="$t('inventory.store_stocks')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header -->
            <PageHeader
                :title="$t('inventory.store_stocks')"
                :subtitle="$t('inventory.store_stocks_subtitle')"
                icon="📊"
            >
                <template #actions>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto">
                        <Link
                            href="/stores"
                            class="h-11 px-4 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center justify-center gap-1.5 transition active:scale-95 shadow-xs border border-slate-200 dark:border-transparent"
                        >
                            <span>←</span>
                            <span>{{ $t('inventory.stores_title') }}</span>
                        </Link>

                        <Link
                            href="/stock-transfers/create"
                            class="w-full sm:w-auto h-11 px-5 rounded-2xl btn-primary-theme font-bold text-xs flex items-center justify-center gap-2 transition transform active:scale-95 cursor-pointer shadow-theme-primary"
                        >
                            <span>🚚</span>
                            <span>{{ $t('inventory.new_transfer') }}</span>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <!-- Store Selector Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <button
                    v-for="st in stores"
                    :key="st.id"
                    type="button"
                    class="h-11 px-4 rounded-2xl text-xs font-bold transition whitespace-nowrap cursor-pointer flex items-center gap-2 border active:scale-95 shadow-xs shrink-0"
                    :class="currentStoreId === st.id ? 'tab-theme-active border-theme-primary' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/80'"
                    @click="switchStore(st.id)"
                >
                    <span>{{ st.type === 'wholesale_van' || st.type === 'van' ? '🚚' : (st.type === 'main_warehouse' || st.type === 'warehouse' ? '🏭' : '🏬') }}</span>
                    <span>{{ st.name }}</span>
                </button>
            </div>

            <!-- Top KPI Cards (Bento Grid on Mobile) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4 font-tajawal">
                <MetricCard
                    :title="$t('inventory.total_items_count')"
                    :value="stocks.total || stocks.data?.length || 0"
                    :currency="$t('inventory.item_unit')"
                    variant="slate"
                />

                <MetricCard
                    :title="`${$t('inventory.low_stock_count')} ⚠️`"
                    :value="lowStockCount"
                    :currency="$t('inventory.item_unit')"
                    variant="danger"
                />

                <div class="col-span-2 sm:col-span-1">
                    <MetricCard
                        :title="$t('inventory.total_inventory_value')"
                        :value="formatMoney(totalValuation)"
                        :currency="$t('common.currency')"
                        variant="success"
                    />
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 font-tajawal">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950 p-1 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs font-bold w-full sm:w-auto">
                        <button
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="stockStatus === 'all' ? 'tab-theme-active' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            @click="stockStatus = 'all'; applyFilters();"
                        >
                            {{ $t('common.all') }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="stockStatus === 'low' ? 'bg-rose-500 text-white font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            @click="stockStatus = 'low'; applyFilters();"
                        >
                            {{ $t('inventory.low_stock_only') }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 sm:flex-none h-9 px-3 rounded-xl transition cursor-pointer active:scale-95"
                            :class="stockStatus === 'out' ? 'bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                            @click="stockStatus = 'out'; applyFilters();"
                        >
                            {{ $t('inventory.out_of_stock_only') }}
                        </button>
                    </div>
                </div>

                <div class="w-full md:w-64">
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('inventory.search_item_placeholder')"
                        class="w-full h-11 px-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-theme-primary focus:outline-none shadow-inner"
                        @input="applyFilters"
                    >
                </div>
            </div>

            <!-- Stocks Table & Mobile Cards -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-4 overflow-hidden font-tajawal">
                <!-- Desktop Table -->
                <div v-if="stocks.data && stocks.data.length > 0" class="hidden md:block overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                                <th class="pb-3">{{ $t('inventory.item_name') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.current_stock') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.min_stock_level') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.purchase_price') }}</th>
                                <th class="pb-3 font-mono">{{ $t('inventory.total_inventory_value') }}</th>
                                <th class="pb-3 text-center">{{ $t('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
                            <tr v-for="st in stocks.data" :key="st.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-3.5">
                                    <div class="font-black text-slate-900 dark:text-white font-tajawal">{{ st.item_name }}</div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ st.item_code }}</div>
                                </td>

                                <td class="py-3.5 font-mono font-black text-sm">
                                    <span
                                        class="px-2.5 py-1 rounded-xl border text-xs"
                                        :class="[
                                            st.quantity <= 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30' :
                                             (st.quantity <= st.min_stock_level ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30' : 'bg-slate-100 dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 border-slate-200 dark:border-slate-700')
                                        ]"
                                    >
                                        {{ st.quantity }} {{ st.unit || 'كجم' }}
                                    </span>
                                </td>

                                <td class="py-3.5 font-mono text-slate-500 dark:text-slate-400 font-bold">
                                    {{ st.min_stock_level }} {{ st.unit || 'كجم' }}
                                </td>

                                <td class="py-3.5 font-mono text-slate-900 dark:text-white font-bold">
                                    {{ formatMoney(st.purchase_price) }} {{ $t('common.currency') }}
                                </td>

                                <td class="py-3.5 font-mono font-black text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(st.total_valuation) }} {{ $t('common.currency') }}
                                </td>

                                <td class="py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="[
                                            st.quantity <= 0 ? 'bg-rose-500/20 text-rose-600 dark:text-rose-400' :
                                             (st.quantity <= st.min_stock_level ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400')
                                        ]"
                                    >
                                        {{ st.quantity <= 0 ? '🔴 نافد' : (st.quantity <= st.min_stock_level ? '⚠️ منخفض' : '🟢 متوفر') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div v-if="stocks.data && stocks.data.length > 0" class="md:hidden space-y-3">
                    <div
                        v-for="st in stocks.data"
                        :key="st.id"
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-2.5 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                            <div>
                                <div class="font-black text-xs text-slate-900 dark:text-white">{{ st.item_name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ st.item_code }}</div>
                            </div>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black"
                                :class="[
                                    st.quantity <= 0 ? 'bg-rose-500/20 text-rose-600 dark:text-rose-400' :
                                     (st.quantity <= st.min_stock_level ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400')
                                ]"
                            >
                                {{ st.quantity <= 0 ? 'نافد' : (st.quantity <= st.min_stock_level ? 'منخفض' : 'متوفر') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                            <div>
                                <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('inventory.current_stock') }}</span>
                                <span class="font-black text-slate-900 dark:text-white">{{ st.quantity }} {{ st.unit }}</span>
                            </div>
                            <div class="text-left">
                                <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('inventory.total_inventory_value') }}</span>
                                <span class="font-black text-emerald-600 dark:text-emerald-400">{{ formatMoney(st.total_valuation) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-if="!stocks.data || stocks.data.length === 0"
                    :title="$t('inventory.no_items_found')"
                    icon="📦"
                />

                <Pagination
                    v-if="stocks.links"
                    :links="stocks.links"
                    :meta="stocks"
                />
            </div>
        </div>
    </AppLayout>
</template>
