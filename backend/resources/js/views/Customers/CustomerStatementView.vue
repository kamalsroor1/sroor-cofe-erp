<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Header & Print Action -->
    <CustomerStatementHeader :customer-name="customer?.name" @print="printStatement" />

    <!-- Summary KPI Profile Cards -->
    <CustomerStatementSummaryCards
      :summary="summary"
      :current-balance="customer?.current_balance || 0"
      :loading="isLoading"
    />

    <!-- Date Range Filter Bar -->
    <CustomerStatementFilterBar
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :active-preset="activePreset"
      @filter="fetchStatement"
      @preset="applyPreset"
    />

    <!-- Ledger Table & Mobile Cards -->

    <template v-if="error && (!ledger || ledger.length === 0)">
      <ErrorState data-testid="error-state" :message="errorMessage" @retry="fetchStatement()" />
    </template>
    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="fetchStatement()" />
      <CustomerStatementTable :ledger="ledger" :loading="isLoading" />
    </template>
  </div>
</template>

<script setup>
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import ErrorState from '../../Components/Common/ErrorState.vue';
import CustomerStatementHeader from '../../Components/Customers/CustomerStatementHeader.vue';
import CustomerStatementSummaryCards from '../../Components/Customers/CustomerStatementSummaryCards.vue';
import CustomerStatementFilterBar from '../../Components/Customers/CustomerStatementFilterBar.vue';
import CustomerStatementTable from '../../Components/Customers/CustomerStatementTable.vue';
import { useCustomerStatement } from '../../Composables/useCustomerStatement';

const {
  customer,
  ledger,
  summary,
  dateFrom,
  dateTo,
  activePreset,
  error,
  errorMessage,
  isLoading,
  applyPreset,
  fetchStatement,
  printStatement,
} = useCustomerStatement();
</script>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
