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

    <template v-if="error && (!customerstatement || customerstatement.length === 0)">
      <ErrorState
        data-testid="error-state"
        :message="errorMessage"
        @retry="fetchCustomerStatement(pagination?.current_page || 1)"
      />
    </template>
    <template v-else>
      <div
        v-if="error"
        class="bg-rose-50 text-rose-500 p-3 rounded-lg mb-4 flex justify-between items-center"
        data-testid="error-state"
      >
        <span>{{ errorMessage }}</span>
        <button
          @click="fetchCustomerStatement(pagination?.current_page || 1)"
          data-testid="retry-button"
          class="underline font-bold"
        >
          {{ $t('connectivity.retry') }}
        </button>
      </div>
      <CustomerStatementTable :ledger="ledger" :loading="isLoading" />
    </template>
  </div>
</template>

<script setup>
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
