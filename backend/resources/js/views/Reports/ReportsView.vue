<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Actions -->
    <PageHeader :title="$t('reports.title')" :subtitle="$t('reports.subtitle')" :icon="BarChart3" help-slug="reports">
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
      :period-error="periodError"
      @set-period="setPeriod"
      @date-change="customDateChanged"
      @update:store-id="fetchReportsData"
      @update:stock-filter="fetchReportsData"
    />

    <!-- Navigation Tabs -->
    <ReportsNavigationTabs :tabs="tabs" v-model:active-tab="activeTab" />

    <!-- Tab Contents -->

    <template v-if="error">
      <ErrorState data-testid="error-state" :message="errorMessage" @retry="fetchReportsData" />
    </template>
    <Transition v-else name="report-tab-fade" mode="out-in">
      <ReportsSalesTab v-if="activeTab === 'sales'" :key="'sales'" :summary="summary" :loading="isLoading" />

      <ReportsItemsTab
        v-else-if="activeTab === 'items'"
        :key="'items'"
        :item-profits="itemsReport.rows.value"
        :totals="itemsReport.totals.value"
        :meta="itemsReport.meta.value"
        :total="itemsTotal"
        :loading="itemsReport.showSkeleton.value"
        :error="itemsReport.error.value"
        :error-message="itemsReport.errorMessage.value"
        @page-change="itemsReport.goToPage"
        @retry="itemsReport.reload"
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
        :items="inventoryReport.rows.value"
        :meta="inventoryReport.meta.value"
        :total="inventoryTotal"
        :loading="isLoading"
        :table-loading="inventoryReport.showSkeleton.value"
        :error="inventoryReport.error.value"
        :error-message="inventoryReport.errorMessage.value"
        @page-change="inventoryReport.goToPage"
        @retry="inventoryReport.reload"
      />

      <ReportsStockDriftTab v-else-if="activeTab === 'stock_drift'" :key="'stock_drift'" />

      <ReportsTreasuryTab
        v-else-if="activeTab === 'treasury'"
        :key="'treasury'"
        :treasury-data="treasuryData"
        :ledger="treasuryReport.rows.value"
        :meta="treasuryReport.meta.value"
        :total="treasuryLedgerTotal"
        :loading="isLoading"
        :ledger-loading="treasuryReport.showSkeleton.value"
        :error="treasuryReport.error.value"
        :error-message="treasuryReport.errorMessage.value"
        @page-change="treasuryReport.goToPage"
        @retry="treasuryReport.reload"
      />
    </Transition>
  </div>
</template>

<script setup>
import ErrorState from '../../Components/Common/ErrorState.vue';
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
import ReportsStockDriftTab from '../../Components/Reports/ReportsStockDriftTab.vue';
import ReportsTreasuryTab from '../../Components/Reports/ReportsTreasuryTab.vue';
import { useReports } from '../../Composables/useReports';

const {
  activeTab,
  error,
  errorMessage,
  isLoading,
  stores,
  filters,
  tabs,
  presets,
  summary,
  periodError,
  itemsReport,
  inventoryReport,
  treasuryReport,
  itemsTotal,
  inventoryTotal,
  treasuryLedgerTotal,
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
