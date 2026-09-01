<template>
  <div class="space-y-6 max-w-7xl mx-auto font-tajawal">
    <!-- Header with AI Badge & Batch Purchase Button -->
    <PageHeader
      :title="$t('purchases.reorder_radar_title')"
      :subtitle="$t('purchases.reorder_radar_subtitle')"
      icon="⚡"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <router-link
            to="/purchases"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
          >
            <ArrowRight class="w-4 h-4" />
            <span>{{ $t('purchases.title') }}</span>
          </router-link>

          <BaseButton
            variant="primary"
            size="md"
            @click="exportToPurchaseOrder"
            :disabled="selectedItems.length === 0"
            class="shadow-lg shadow-theme-primary font-black"
          >
            <ShoppingCart class="w-4 h-4" />
            <span>{{ $t('purchases.create_batch_po_btn', { count: selectedItems.length }) }}</span>
          </BaseButton>
        </div>
      </template>
    </PageHeader>

    <!-- Urgency & Financial Radar Metrics -->
    <SmartReorderMetricsGrid
      :metrics="metrics"
      :loading="isLoading"
    />

    <!-- Filter Controls Bar -->
    <SmartReorderFilterBar
      v-model:search-query="searchQuery"
      v-model:analysis-days="analysisDays"
      v-model:target-cover-days="targetCoverDays"
      v-model:selected-urgency="selectedUrgency"
      :analysis-days-options="analysisDaysOptions"
      :target-cover-options="targetCoverOptions"
      :urgency-options="urgencyOptions"
      @search="debounceFetch"
      @refresh="fetchSuggestions"
    />

    <!-- Reorder Suggestions Table & Mobile Cards -->
    <SmartReorderTable
      :suggestions="suggestions"
      :selected-ids="selectedIds"
      :is-all-selected="isAllSelected"
      :loading="isLoading"
      @toggle-select-all="toggleSelectAll"
      @toggle-item="toggleItem"
    />
  </div>
</template>

<script setup>
import { ArrowRight, ShoppingCart } from 'lucide-vue-next';
import PageHeader from '../../Components/Common/PageHeader.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import SmartReorderMetricsGrid from '../../Components/SmartReorder/SmartReorderMetricsGrid.vue';
import SmartReorderFilterBar from '../../Components/SmartReorder/SmartReorderFilterBar.vue';
import SmartReorderTable from '../../Components/SmartReorder/SmartReorderTable.vue';
import { useSmartReorder } from '../../Composables/useSmartReorder';

const {
  suggestions,
  metrics,
  analysisDays,
  targetCoverDays,
  selectedUrgency,
  searchQuery,
  isLoading,
  selectedItems,
  selectedIds,
  isAllSelected,
  analysisDaysOptions,
  targetCoverOptions,
  urgencyOptions,
  toggleSelectAll,
  toggleItem,
  fetchSuggestions,
  debounceFetch,
  exportToPurchaseOrder,
} = useSmartReorder();
</script>
