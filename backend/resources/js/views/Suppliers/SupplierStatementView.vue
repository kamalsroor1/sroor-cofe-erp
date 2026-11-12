<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Header & Print Action -->
    <SupplierStatementHeader :supplier-name="supplier?.name" @print="printStatement" />

    <ErrorState v-if="error && !supplier" :message="errorMessage" :loading="isLoading" @retry="retry" />

    <template v-else>
      <InlineErrorBar v-if="error" :message="errorMessage" @retry="retry" />

      <!-- Summary KPI Profile Cards -->
      <SupplierStatementSummaryCards
        :summary="summary"
        :current-balance="supplier?.current_balance ?? '0.000'"
        :loading="isLoading"
      />

      <!-- Date Range Filter Bar -->
      <SupplierStatementFilterBar
        v-model:date-from="dateFrom"
        v-model:date-to="dateTo"
        :active-preset="activePreset"
        @filter="fetchStatement"
        @preset="applyPreset"
      />

      <!-- Ledger Table & Mobile Cards -->
      <SupplierStatementTable :ledger="rows" :loading="isLoading" />
    </template>
  </div>
</template>

<script setup>
import ErrorState from '../../Components/Common/ErrorState.vue';
import InlineErrorBar from '../../Components/Common/InlineErrorBar.vue';
import SupplierStatementHeader from '../../Components/Suppliers/SupplierStatementHeader.vue';
import SupplierStatementSummaryCards from '../../Components/Suppliers/SupplierStatementSummaryCards.vue';
import SupplierStatementFilterBar from '../../Components/Suppliers/SupplierStatementFilterBar.vue';
import SupplierStatementTable from '../../Components/Suppliers/SupplierStatementTable.vue';
import { useSupplierStatement } from '../../Composables/useSupplierStatement';

const {
  supplier,
  rows,
  summary,
  dateFrom,
  dateTo,
  activePreset,
  isLoading,
  error,
  errorMessage,
  retry,
  applyPreset,
  fetchStatement,
  printStatement,
} = useSupplierStatement();
</script>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
