<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal text-slate-900 dark:text-slate-100">
    <!-- Header with Filter toggle, Actions dropdown, and Fast POS -->
    <PageHeader
      :title="$t('invoices.title')"
      :subtitle="$t('invoices.subtitle')"
      :icon="'shopping-cart'"
      help-slug="pos"
    >
      <template #actions>
        <PageActions :items="headerActions" />
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
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { SlidersHorizontal, FileSpreadsheet, Download, Printer, RefreshCw, Zap } from 'lucide-vue-next';
import { useInvoices } from '../../Composables/useInvoices';
import { useTrans } from '../../Composables/useTrans';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import PageHeader from '../../Components/Common/PageHeader.vue';
import PageActions from '../../Components/Common/PageActions.vue';
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

const { t } = useTrans();
const headerActions = computed(() => [
  {
    key: 'filter',
    label: t('invoices.filter_search'),
    icon: SlidersHorizontal,
    iconClass: isFilterSidebarOpen.value || activeFiltersCount.value > 0 ? 'text-theme-primary' : 'text-slate-500',
    active: isFilterSidebarOpen.value || activeFiltersCount.value > 0,
    badge: activeFiltersCount.value > 0 ? activeFiltersCount.value : null,
    onClick: () => (isFilterSidebarOpen.value = !isFilterSidebarOpen.value),
  },
  {
    key: 'options',
    label: t('invoices.options_and_actions'),
    icon: FileSpreadsheet,
    iconClass: 'text-emerald-500',
    children: [
      { label: t('invoices.export_excel_csv'), icon: Download, iconClass: 'text-emerald-500', onClick: exportInvoices },
      { label: t('invoices.print_filtered_report'), icon: Printer, iconClass: 'text-cyan-500', onClick: printReport },
      {
        label: t('invoices.refresh_now'),
        icon: RefreshCw,
        iconClass: 'text-theme-primary',
        onClick: refreshCurrentPage,
      },
    ],
  },
  { key: 'pos', label: t('invoices.pos_fast_badge'), icon: Zap, primary: true, to: '/pos' },
]);

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
