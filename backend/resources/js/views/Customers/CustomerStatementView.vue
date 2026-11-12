<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <div class="space-y-6 print:hidden">
      <CustomerStatementHeader :customer-name="customer?.name" :printing="printing.active" @print="printStatement" />

      <CustomerStatementSummaryCards
        :summary="summary"
        :current-balance="customer?.current_balance ?? '0.000'"
        :loading="isLoading && !customer"
      />

      <CustomerStatementFilterBar
        v-model:date-from="dateFrom"
        v-model:date-to="dateTo"
        :active-preset="activePreset"
        :range-error="rangeError"
        @filter="applyFilter"
        @preset="applyPreset"
      />

      <CustomerStatementPrintProgress
        v-if="printing.active"
        :loaded="printing.loaded"
        :total="printing.total"
        @cancel="cancelPrint"
      />

      <template v-if="error && ledger.length === 0">
        <ErrorState data-testid="error-state" :message="errorMessage" @retry="reload" />
      </template>
      <section v-else ref="tableSection">
        <InlineErrorBar v-if="error" :message="errorMessage" @retry="reload" />
        <CustomerStatementTable
          :ledger="rows"
          :loading="isLoading"
          :pagination="meta"
          :offset="pageOffset"
          :has-opening="hasOpening"
          @page-change="changePage"
        />
      </section>
    </div>

    <CustomerStatementPrintSheet
      :customer="customer"
      :rows="printRows"
      :summary="printSummary"
      :period-text="periodText"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import CustomerStatementHeader from '../../Components/Customers/CustomerStatementHeader.vue';
import CustomerStatementSummaryCards from '../../Components/Customers/CustomerStatementSummaryCards.vue';
import CustomerStatementFilterBar from '../../Components/Customers/CustomerStatementFilterBar.vue';
import CustomerStatementTable from '../../Components/Customers/CustomerStatementTable.vue';
import CustomerStatementPrintProgress from '../../Components/Customers/CustomerStatementPrintProgress.vue';
import CustomerStatementPrintSheet from '../../Components/Customers/CustomerStatementPrintSheet.vue';
import { useCustomerStatement } from '../../Composables/useCustomerStatement';

const {
  customer,
  ledger,
  rows,
  hasOpening,
  pageOffset,
  summary,
  meta,
  periodText,
  dateFrom,
  dateTo,
  activePreset,
  error,
  errorMessage,
  rangeError,
  isLoading,
  printing,
  printRows,
  printSummary,
  applyPreset,
  applyFilter,
  goToPage,
  reload,
  printStatement,
  cancelPrint,
} = useCustomerStatement();

const tableSection = ref(null);

const changePage = (page) => {
  goToPage(page);
  tableSection.value?.scrollIntoView({ block: 'start', behavior: 'smooth' });
};
</script>
