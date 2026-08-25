<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Actions -->
    <PageHeader
      :title="$t('reports.title')"
      :subtitle="$t('reports.subtitle')"
      :icon="BarChart3"
    >
      <template #actions>
        <button
          type="button"
          @click="printReport"
          class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
        >
          <Printer class="w-4 h-4" />
          <span>{{ $t('reports.print_a4_report') }}</span>
        </button>
      </template>
    </PageHeader>

    <!-- Global Filter Bar -->
    <ReportsFilterBar
      :presets="presets"
      :period="filters.period"
      v-model:from="filters.from"
      v-model:to="filters.to"
      v-model:store-id="filters.store_id"
      v-model:stock-filter="filters.stock_filter"
      :stores="stores"
      :active-tab="activeTab"
      @set-period="setPeriod"
      @date-change="customDateChanged"
      @update:store-id="fetchReportsData"
      @update:stock-filter="fetchReportsData"
    />

    <!-- Navigation Tabs -->
    <ReportsNavigationTabs
      :tabs="tabs"
      v-model:active-tab="activeTab"
    />

    <!-- Tab Contents -->
    <Transition name="report-tab-fade" mode="out-in">
      <ReportsSalesTab
        v-if="activeTab === 'sales'"
        :key="'sales'"
        :summary="summary"
        :loading="isLoading"
      />

      <ReportsItemsTab
        v-else-if="activeTab === 'items'"
        :key="'items'"
        :item-profits="itemProfits"
        :loading="isLoading"
      />

      <ReportsStoresTab
        v-else-if="activeTab === 'stores'"
        :key="'stores'"
        :store-breakdown="storeBreakdown"
        :loading="isLoading"
      />

      <ReportsCustomersTab
        v-else-if="activeTab === 'customers'"
        :key="'customers'"
        :customer-sales="customerSales"
        :loading="isLoading"
      />

      <ReportsExpensesTab
        v-else-if="activeTab === 'expenses'"
        :key="'expenses'"
        :expenses-breakdown="expensesBreakdown"
        :loading="isLoading"
      />

      <ReportsInventoryTab
        v-else-if="activeTab === 'inventory'"
        :key="'inventory'"
        :inventory-data="inventoryData"
        :loading="isLoading"
      />

      <ReportsTreasuryTab
        v-else-if="activeTab === 'treasury'"
        :key="'treasury'"
        :treasury-data="treasuryData"
        :loading="isLoading"
      />
    </Transition>
  </div>
</template>

<script setup>
import { Printer, BarChart3 } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReportsFilterBar from '../../Components/Reports/ReportsFilterBar.vue';
import ReportsNavigationTabs from '../../Components/Reports/ReportsNavigationTabs.vue';
import ReportsSalesTab from '../../Components/Reports/ReportsSalesTab.vue';
import ReportsItemsTab from '../../Components/Reports/ReportsItemsTab.vue';
import ReportsStoresTab from '../../Components/Reports/ReportsStoresTab.vue';
import ReportsCustomersTab from '../../Components/Reports/ReportsCustomersTab.vue';
import ReportsExpensesTab from '../../Components/Reports/ReportsExpensesTab.vue';
import ReportsInventoryTab from '../../Components/Reports/ReportsInventoryTab.vue';
import ReportsTreasuryTab from '../../Components/Reports/ReportsTreasuryTab.vue';
import { useReports } from '../../Composables/useReports';

const {
  activeTab,
  isLoading,
  stores,
  filters,
  tabs,
  presets,
  summary,
  itemProfits,
  storeBreakdown,
  customerSales,
  expensesBreakdown,
  inventoryData,
  treasuryData,
  setPeriod,
  customDateChanged,
  fetchReportsData,
  printReport,
} = useReports();
</script>
