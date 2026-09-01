<template>
  <div class="space-y-6 max-w-5xl mx-auto font-tajawal">
    <!-- Header & Action Bar -->
    <PageHeader
      :title="`${$t('inventory.movements_title')}: ${item?.name || ''}`"
      :subtitle="$t('inventory.movements_subtitle')"
      icon="📜"
      class="no-print"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <router-link
            to="/items"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('inventory.items_list') }}</span>
          </router-link>

          <BaseButton
            variant="secondary"
            size="md"
            @click="printReport"
            class="font-bold flex items-center gap-1.5"
          >
            <Printer class="w-4 h-4 text-theme-primary" />
            <span>{{ $t('common.print') }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Financial & Movement Summary Cards -->
    <ItemMovementsSummaryCards
      :stats="stats"
      :unit="item?.unit || ''"
      :current-stock="item?.current_stock || 0"
      :loading="isLoading"
    />

    <!-- Date Range & Movement Filter Bar -->
    <ItemMovementsFilterBar
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :active-preset="activePreset"
      @filter="fetchMovements"
      @apply-preset="applyPreset"
    />

    <!-- Movements Ledger Table -->
    <ItemMovementsTable
      :movements="movements"
      :loading="isLoading"
      :get-movement-badge="getMovementBadge"
      :format-movement-label="formatMovementLabel"
      :is-positive-movement="isPositiveMovement"
    />
  </div>
</template>

<script setup>
import { ArrowRight, Printer } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import ItemMovementsSummaryCards from '../../Components/ItemMovements/ItemMovementsSummaryCards.vue';
import ItemMovementsFilterBar from '../../Components/ItemMovements/ItemMovementsFilterBar.vue';
import ItemMovementsTable from '../../Components/ItemMovements/ItemMovementsTable.vue';
import { useItemMovements } from '../../Composables/useItemMovements';

const {
  item,
  movements,
  stats,
  dateFrom,
  dateTo,
  activePreset,
  isLoading,
  formatMovementLabel,
  isPositiveMovement,
  getMovementBadge,
  applyPreset,
  fetchMovements,
  printReport,
} = useItemMovements();
</script>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
