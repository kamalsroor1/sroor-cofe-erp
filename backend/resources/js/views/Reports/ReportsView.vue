<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Page Header & Actions -->
    <PageHeader :title="$t('reports.title')" :subtitle="$t('reports.subtitle')" :icon="BarChart3" help-slug="reports">
      <template #actions>
        <button
          v-if="canExportTab"
          type="button"
          :disabled="breakdowns.exporting.value === activeTab"
          :data-testid="`export-${activeTab}`"
          @click="breakdowns.exportReport(activeTab)"
          class="min-h-[38px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95 disabled:opacity-60 disabled:cursor-wait"
        >
          <Loader2 v-if="breakdowns.exporting.value === activeTab" class="w-4 h-4 animate-spin" aria-hidden="true" />
          <Download v-else class="w-4 h-4" aria-hidden="true" />
          <span>{{ $t('reports.export_csv') }}</span>
        </button>
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

      <ReportsLockedSection v-else-if="activeTab === 'items' && itemsLocked" :key="'items-locked'" />

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

      <ReportsLockedSection v-else-if="activeTab === 'stores' && storesLocked" :key="'stores-locked'" />

      <ReportsStoresTab
        v-else-if="activeTab === 'stores'"
        :key="'stores'"
        :store-breakdown="storeBreakdown"
        :loading="isLoading"
      />

      <ReportsBreakdownTab
        v-else-if="activeBreakdown"
        :key="activeTab"
        :report-key="activeTab"
        :title="activeBreakdown.definition.title"
        :hint="breakdownHint"
        :columns="activeBreakdown.definition.columns"
        :rows="activeBreakdown.report.rows.value"
        :totals="activeBreakdown.report.totals.value"
        :meta="activeBreakdown.report.meta.value"
        :loading="activeBreakdown.report.showSkeleton.value"
        :error="activeBreakdown.report.error.value"
        :error-message="activeBreakdown.report.errorMessage.value"
        :exporting="breakdowns.exporting.value === activeTab"
        :can-export="breakdowns.canExport.value"
        @page-change="activeBreakdown.report.goToPage"
        @retry="activeBreakdown.report.reload"
        @export="breakdowns.exportReport(activeTab)"
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
import { Printer, BarChart3, Download, Loader2 } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import ReportsFilterBar from '../../Components/Reports/ReportsFilterBar.vue';
import ReportsNavigationTabs from '../../Components/Reports/ReportsNavigationTabs.vue';
import ReportsSalesTab from '../../Components/Reports/ReportsSalesTab.vue';
import ReportsLockedSection from '../../Components/Reports/ReportsLockedSection.vue';
import ReportsItemsTab from '../../Components/Reports/ReportsItemsTab.vue';
import ReportsStoresTab from '../../Components/Reports/ReportsStoresTab.vue';
import ReportsCustomersTab from '../../Components/Reports/ReportsCustomersTab.vue';
import ReportsExpensesTab from '../../Components/Reports/ReportsExpensesTab.vue';
import ReportsInventoryTab from '../../Components/Reports/ReportsInventoryTab.vue';
import ReportsStockDriftTab from '../../Components/Reports/ReportsStockDriftTab.vue';
import ReportsTreasuryTab from '../../Components/Reports/ReportsTreasuryTab.vue';
import ReportsBreakdownTab from '../../Components/Reports/ReportsBreakdownTab.vue';
import { computed } from 'vue';
import { useReports } from '../../Composables/useReports';
import { EXPORTABLE_TABS } from '../../Composables/useReportBreakdowns';

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
  itemsLocked,
  storesLocked,
  breakdowns,
  setPeriod,
  customDateChanged,
  fetchReportsData,
  printReport,
} = useReports();

// SHOP-05: CSV of the P&L summary (sales tab) and item profitability (items tab, Pro only).
const canExportTab = computed(
  () =>
    breakdowns.canExport.value &&
    EXPORTABLE_TABS.includes(activeTab.value) &&
    !(activeTab.value === 'items' && itemsLocked.value)
);
// SHOP-05: the four tabular reports share one table component, driven by column definitions.
const activeBreakdown = computed(() => {
  const report = breakdowns.reports[activeTab.value];
  return report ? { report, definition: breakdowns.definitions.value[activeTab.value] } : null;
});
const breakdownHint = computed(() => {
  const hint = activeBreakdown.value?.definition.hint;
  return typeof hint === 'function' ? hint(activeBreakdown.value.report.extra.value?.as_of) : hint || '';
});
</script>
