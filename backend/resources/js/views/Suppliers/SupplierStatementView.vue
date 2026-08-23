<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Header & Print Action -->
    <SupplierStatementHeader
      :supplier-name="supplier?.name"
      @print="printStatement"
    />

    <!-- Summary KPI Profile Cards -->
    <SupplierStatementSummaryCards
      :summary="summary"
      :current-balance="supplier?.current_balance || 0"
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
    <SupplierStatementTable
      :ledger="ledger"
      :loading="isLoading"
    />
  </div>
</template>

<script setup>
import SupplierStatementHeader from '../../Components/Suppliers/SupplierStatementHeader.vue';
import SupplierStatementSummaryCards from '../../Components/Suppliers/SupplierStatementSummaryCards.vue';
import SupplierStatementFilterBar from '../../Components/Suppliers/SupplierStatementFilterBar.vue';
import SupplierStatementTable from '../../Components/Suppliers/SupplierStatementTable.vue';
import { useSupplierStatement } from '../../Composables/useSupplierStatement';

const {
  supplier,
  ledger,
  summary,
  dateFrom,
  dateTo,
  activePreset,
  isLoading,
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
