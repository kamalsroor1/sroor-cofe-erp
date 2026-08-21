<script setup>
import { ref } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';

// Skeleton Components
import TableSkeleton from '@/Components/Common/Skeletons/TableSkeleton.vue';
import CardSkeleton from '@/Components/Common/Skeletons/CardSkeleton.vue';
import StatCardSkeleton from '@/Components/Common/Skeletons/StatCardSkeleton.vue';

// Atomic Sub-Components
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import ReportSalesTab from '@/Components/Reports/ReportSalesTab.vue';
import ReportItemsTab from '@/Components/Reports/ReportItemsTab.vue';
import ReportStoresTab from '@/Components/Reports/ReportStoresTab.vue';
import ReportCustomersTab from '@/Components/Reports/ReportCustomersTab.vue';
import ReportExpensesTab from '@/Components/Reports/ReportExpensesTab.vue';
import ReportInventoryTab from '@/Components/Reports/ReportInventoryTab.vue';
import ReportTreasuryTab from '@/Components/Reports/ReportTreasuryTab.vue';

const props = defineProps({
    active_tab: { type: String, default: 'sales' },
    summary: { type: Object, default: () => null },
    item_profits: { type: Array, default: () => [] },
    store_breakdown: { type: Array, default: () => [] },
    customer_sales: { type: Array, default: () => [] },
    expenses_breakdown: { type: Array, default: () => [] },
    inventory_items: { type: Array, default: () => [] },
    abc_data: { type: Object, default: () => null },
    treasury_data: { type: Object, default: () => null },
    stores: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const currentTab = ref(props.active_tab || 'sales');

const filterForm = ref({
    tab: currentTab.value,
    period: props.filters.period || 'this_month',
    from: props.filters.from || '',
    to: props.filters.to || '',
    store_id: props.filters.store_id || 'all',
    treasury_method: props.filters.treasury_method || 'all',
    stock_filter: props.filters.stock_filter || 'all',
});

const applyFilters = () => {
    filterForm.value.tab = currentTab.value;
    router.get('/reports', filterForm.value, {
        preserveState: true,
        replace: true,
    });
};

const switchTab = (tab) => {
    currentTab.value = tab;
    filterForm.value.tab = tab;
    applyFilters();
};

const setPeriod = (period) => {
    filterForm.value.period = period;
    if (period === 'today') {
        const today = new Date().toISOString().split('T')[0];
        filterForm.value.from = today;
        filterForm.value.to = today;
    } else if (period === 'yesterday') {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        const y = d.toISOString().split('T')[0];
        filterForm.value.from = y;
        filterForm.value.to = y;
    } else if (period === 'this_week') {
        const d = new Date();
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        const mon = new Date(d.setDate(diff)).toISOString().split('T')[0];
        filterForm.value.from = mon;
        filterForm.value.to = new Date().toISOString().split('T')[0];
    } else if (period === 'this_month') {
        const d = new Date();
        const firstDay = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        filterForm.value.from = firstDay;
        filterForm.value.to = new Date().toISOString().split('T')[0];
    } else if (period === 'this_year') {
        const d = new Date();
        const firstDay = new Date(d.getFullYear(), 0, 1).toISOString().split('T')[0];
        filterForm.value.from = firstDay;
        filterForm.value.to = new Date().toISOString().split('T')[0];
    }
    applyFilters();
};

const exportAbc = () => {
    const params = new URLSearchParams({
        from: filterForm.value.from,
        to: filterForm.value.to,
        store_id: filterForm.value.store_id,
    });
    window.location.href = `/reports/export-abc?${params.toString()}`;
};

const printReport = () => {
    const params = new URLSearchParams({
        from: filterForm.value.from,
        to: filterForm.value.to,
        store_id: filterForm.value.store_id,
    });
    window.open(`/reports/print?${params.toString()}`, '_blank');
};
</script>

<template>
    <Head :title="$t('reports.title')" />

    <AppLayout>
        <div class="space-y-6 font-tajawal">
            <!-- Header Banner -->
            <PageHeader
                :title="$t('reports.title')"
                :subtitle="$t('reports.subtitle')"
                icon="📊"
            >
                <template #actions>
                    <button
                        @click="printReport"
                        type="button"
                        class="w-full md:w-auto h-11 px-5 rounded-2xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-white border border-slate-200 dark:border-slate-700 font-bold text-xs flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer shadow-xs"
                    >
                        <span>🖨️</span>
                        <span>{{ $t('reports.print_full_report') }}</span>
                    </button>
                </template>
            </PageHeader>

            <!-- Global Filter Bar -->
            <ReportFilterBar
                :filter-form="filterForm"
                :stores="stores"
                @apply="applyFilters"
                @set-period="setPeriod"
            />

            <!-- 7 Navigation Tabs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-1.5 sm:gap-2 bg-slate-100 dark:bg-slate-950 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs font-tajawal">
                <button
                    v-for="tab in [
                        { id: 'sales', label: $t('reports.tab_sales'), icon: '💵' },
                        { id: 'items', label: $t('reports.tab_items'), icon: '📦' },
                        { id: 'stores', label: $t('reports.tab_stores'), icon: '🏢' },
                        { id: 'customers', label: $t('reports.tab_customers'), icon: '👥' },
                        { id: 'expenses', label: $t('reports.tab_expenses'), icon: '💸' },
                        { id: 'inventory', label: $t('reports.tab_inventory'), icon: '📈' },
                        { id: 'treasury', label: $t('reports.tab_treasury'), icon: '💰' },
                    ]"
                    :key="tab.id"
                    @click="switchTab(tab.id)"
                    type="button"
                    class="py-2.5 px-2 rounded-xl font-bold transition active:scale-95 cursor-pointer flex flex-col sm:flex-row items-center justify-center gap-1.5 text-center min-h-[44px]"
                    :class="currentTab === tab.id ? 'tab-theme-active' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                >
                    <span class="text-sm">{{ tab.icon }}</span>
                    <span>{{ tab.label }}</span>
                </button>
            </div>

            <!-- Tab 1: Sales & P&L -->
            <Deferred v-if="currentTab === 'sales'" data="summary">
                <template #fallback>
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <StatCardSkeleton v-for="i in 4" :key="i" />
                        </div>
                        <CardSkeleton :rows="6" />
                    </div>
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportSalesTab
                        v-if="summary"
                        :summary="summary"
                    />
                </div>
            </Deferred>

            <!-- Tab 2: Item Profits -->
            <Deferred v-if="currentTab === 'items'" data="item_profits">
                <template #fallback>
                    <TableSkeleton :columns-count="7" :rows-count="6" />
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportItemsTab
                        :item-profits="item_profits"
                    />
                </div>
            </Deferred>

            <!-- Tab 3: Store Comparison -->
            <Deferred v-if="currentTab === 'stores'" data="store_breakdown">
                <template #fallback>
                    <TableSkeleton :columns-count="8" :rows-count="4" />
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportStoresTab
                        :store-breakdown="store_breakdown"
                    />
                </div>
            </Deferred>

            <!-- Tab 4: Customer Sales -->
            <Deferred v-if="currentTab === 'customers'" :data="['customer_sales', 'summary']">
                <template #fallback>
                    <TableSkeleton :columns-count="7" :rows-count="5" />
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportCustomersTab
                        v-if="summary"
                        :customer-sales="customer_sales"
                        :total-customers-debt="summary.total_customers_debt"
                    />
                </div>
            </Deferred>

            <!-- Tab 5: Expenses Breakdown -->
            <Deferred v-if="currentTab === 'expenses'" :data="['expenses_breakdown', 'summary']">
                <template #fallback>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <StatCardSkeleton v-for="i in 3" :key="i" />
                        </div>
                        <TableSkeleton :columns-count="4" :rows-count="5" />
                    </div>
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportExpensesTab
                        v-if="summary"
                        :expenses-breakdown="expenses_breakdown"
                        :total-expenses="summary.total_expenses"
                    />
                </div>
            </Deferred>

            <!-- Tab 6: Inventory Valuation & ABC -->
            <Deferred v-if="currentTab === 'inventory'" :data="['abc_data', 'summary']">
                <template #fallback>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <StatCardSkeleton v-for="i in 3" :key="i" />
                        </div>
                        <CardSkeleton :rows="5" />
                    </div>
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportInventoryTab
                        v-if="summary && abc_data"
                        :summary="summary"
                        :abc-data="abc_data"
                        @export-abc="exportAbc"
                    />
                </div>
            </Deferred>

            <!-- Tab 7: Treasury Liquidity -->
            <Deferred v-if="currentTab === 'treasury'" data="treasury_data">
                <template #fallback>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <StatCardSkeleton v-for="i in 3" :key="i" />
                        </div>
                        <CardSkeleton :rows="6" />
                    </div>
                </template>
                <div class="animate-in fade-in duration-500">
                    <ReportTreasuryTab
                        v-if="treasury_data"
                        :treasury-data="treasury_data"
                    />
                </div>
            </Deferred>
        </div>
    </AppLayout>
</template>