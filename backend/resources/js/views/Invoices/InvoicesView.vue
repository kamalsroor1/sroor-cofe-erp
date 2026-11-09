<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal text-slate-900 dark:text-slate-100">
    <!-- Header with Filter toggle, Actions dropdown, and Fast POS -->
    <PageHeader :title="$t('invoices.title')" :subtitle="$t('invoices.subtitle')" :icon="'🛒'">
      <template #actions>
        <div class="flex items-center gap-2 flex-wrap">
          <BaseButton
            :active="isFilterSidebarOpen || activeFiltersCount > 0"
            :icon="SlidersHorizontal"
            :icon-class="isFilterSidebarOpen || activeFiltersCount > 0 ? 'text-theme-primary' : 'text-slate-500'"
            :label="$t('invoices.filter_search')"
            :badge="activeFiltersCount > 0 ? activeFiltersCount : null"
            @click="isFilterSidebarOpen = !isFilterSidebarOpen"
          />

          <BaseDropdown
            :label="$t('invoices.options_and_actions')"
            :icon="FileSpreadsheet"
            icon-class="text-emerald-500"
            align="end"
            :items="[
              {
                label: $t('invoices.export_excel_csv'),
                icon: Download,
                iconColor: 'text-emerald-500',
                onClick: () => exportInvoices(),
              },
              {
                label: $t('invoices.print_filtered_report'),
                icon: Printer,
                iconColor: 'text-cyan-500',
                onClick: printReport,
              },
              {
                label: $t('invoices.refresh_now'),
                icon: RefreshCw,
                iconColor: 'text-theme-primary',
                onClick: refreshCurrentPage,
              },
            ]"
          />

          <BaseButton to="/pos" variant="gradient" :icon="Zap" :label="$t('invoices.pos_fast_badge')" />
        </div>
      </template>
    </PageHeader>

    <!-- Financial Metrics Cards -->
    <InvoicesMetricsCards :summary="summary" :is-loading="isLoading" />

    <!-- Workspace: Search + Table + Sidebar -->
    <div class="flex flex-col lg:flex-row gap-5 items-start">
      <div class="flex-1 w-full space-y-4 min-w-0">
        <InvoicesQuickSearch
          v-model="searchQuery"
          :active-preset="activeDatePreset"
          :presets="datePresets"
          @select-preset="applyDatePreset"
          @update:model-value="debounceSearch"
        />
        <InvoicesBulkActionsBar
          :selected-count="selectedInvoiceIds.length"
          @bulk-print="bulkPrintReceipts"
          @bulk-export="exportSelected"
          @bulk-cancel="bulkCancelSelected"
          @deselect-all="selectedInvoiceIds = []"
        />

        <template v-if="error && (!invoices || invoices.length === 0)">
          <ErrorState data-testid="error-state" :message="errorMessage" @retry="refreshCurrentPage" />
        </template>
        <template v-else>
          <InlineErrorBar v-if="error" :message="errorMessage" @retry="refreshCurrentPage" />
          <InvoicesTable
            :invoices="invoices"
            :is-loading="isLoading"
            :selected-ids="selectedInvoiceIds"
            :is-all-selected="isAllSelected"
            :pagination="pagination"
            @toggle-select="toggleSelectInvoice"
            @toggle-select-all="toggleSelectAll"
            @preview="openDetailsModal"
            @print="openPrintReceipt"
            @cancel="cancelInvoice"
            @change-page="fetchInvoices"
            @reset-filters="resetAllFilters"
          />
        </template>
      </div>

      <InvoicesFilterSidebar
        :is-open="isFilterSidebarOpen"
        v-model:store-id="selectedStoreId"
        v-model:payment-type="selectedPaymentType"
        v-model:status="selectedStatus"
        v-model:date-from="dateFrom"
        v-model:date-to="dateTo"
        :store-options="storeOptions"
        :payment-type-options="paymentTypeOptions"
        :status-options="statusOptions"
        @close="isFilterSidebarOpen = false"
        @apply="fetchInvoices(1)"
        @reset="resetAllFilters"
      />
    </div>

    <!-- Invoice Details Modal -->
    <InvoiceDetailsModal
      :show="showDetailsModal"
      :invoice="selectedInvoiceDetails"
      :whats-app="whatsAppData"
      @close="showDetailsModal = false"
      @print="openPrintReceipt"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { SlidersHorizontal, FileSpreadsheet, Download, Printer, RefreshCw, Zap } from 'lucide-vue-next';
import { useInvoices } from '../../Composables/useInvoices';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import BaseDropdown from '../../Components/Common/BaseDropdown.vue';
import InvoicesMetricsCards from '../../Components/Invoices/InvoicesMetricsCards.vue';
import InvoicesQuickSearch from '../../Components/Invoices/InvoicesQuickSearch.vue';
import InvoicesBulkActionsBar from '../../Components/Invoices/InvoicesBulkActionsBar.vue';
import InvoicesFilterSidebar from '../../Components/Invoices/InvoicesFilterSidebar.vue';
import InvoicesTable from '../../Components/Invoices/InvoicesTable.vue';
import InvoiceDetailsModal from '../../Components/Invoices/InvoiceDetailsModal.vue';

const route = useRoute();
const router = useRouter();
const isFilterSidebarOpen = ref(false);

const {
  invoices,
  summary,
  pagination,
  isLoading,
  error,
  errorMessage,
  searchQuery,
  selectedStoreId,
  selectedPaymentType,
  selectedStatus,
  dateFrom,
  dateTo,
  activeDatePreset,
  selectedInvoiceIds,
  selectedInvoiceDetails,
  whatsAppData,
  showDetailsModal,
  storeOptions,
  paymentTypeOptions,
  statusOptions,
  datePresets,
  activeFiltersCount,
  isAllSelected,
  fetchInvoices,
  refreshCurrentPage,
  debounceSearch,
  applyDatePreset,
  resetAllFilters,
  toggleSelectInvoice,
  toggleSelectAll,
  openDetailsModal,
  openPrintReceipt,
  bulkPrintReceipts,
  cancelInvoice,
  bulkCancelSelected,
  exportInvoices,
  exportSelected,
} = useInvoices();

const printReport = () => window.print();

// Deep link from the dashboard: /invoices?view=ID opens that invoice's preview, then drops the param.
const openLinkedInvoice = async () => {
  const id = Number(route.query.view);
  if (!Number.isInteger(id) || id <= 0) return;
  await openDetailsModal({ id });
  router.replace({ path: route.path, query: { ...route.query, view: undefined } });
};

watch(() => route.query.view, openLinkedInvoice);
onMounted(() => {
  fetchInvoices(1);
  openLinkedInvoice();
});
</script>
