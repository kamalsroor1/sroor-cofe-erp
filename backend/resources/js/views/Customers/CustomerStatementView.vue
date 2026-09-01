<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Header & Print Action -->
    <CustomerStatementHeader
      :customer-name="customer?.name"
      @print="printStatement"
    />

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
    <CustomerStatementTable
      :ledger="ledger"
      :loading="isLoading"
    />
  </div>
</template>

<script setup>
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
