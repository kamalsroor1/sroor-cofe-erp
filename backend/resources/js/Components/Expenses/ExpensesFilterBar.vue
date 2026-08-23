<template>
  <div class="space-y-3 font-tajawal">
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
      <!-- Search Input -->
      <div class="flex-1">
        <BaseSearchInput
          :model-value="searchQuery"
          @update:model-value="$emit('update:searchQuery', $event)"
          :placeholder="$t('expenses.search_placeholder')"
          :debounce="300"
          @search="$emit('search')"
        />
      </div>

      <!-- Cost Center Dropdown -->
      <div class="w-full md:w-56">
        <BaseSelect
          :model-value="selectedCostCenter"
          @update:model-value="$emit('update:selectedCostCenter', $event)"
          :options="costCenterFilterOptions"
          :searchable="false"
          @change="$emit('filter')"
        />
      </div>

      <!-- Date Range Filter -->
      <div class="flex items-center gap-2">
        <BaseInput
          :model-value="dateFrom"
          @update:model-value="$emit('update:dateFrom', $event)"
          type="date"
          input-class="min-h-[44px] text-xs font-mono"
          @change="$emit('filter')"
        />
        <span class="text-xs text-slate-500 font-bold">—</span>
        <BaseInput
          :model-value="dateTo"
          @update:model-value="$emit('update:dateTo', $event)"
          type="date"
          input-class="min-h-[44px] text-xs font-mono"
          @change="$emit('filter')"
        />
      </div>
    </div>

    <!-- Quick Category Chips -->
    <div v-if="quickCategories.length > 0" class="flex items-center gap-2 overflow-x-auto pb-1">
      <span class="text-xs font-bold text-slate-500 dark:text-slate-400 shrink-0 font-tajawal">{{ $t('expenses.quick_categories_label') }}</span>
      <button
        v-for="cat in quickCategories"
        :key="cat"
        type="button"
        @click="$emit('filter-category', cat)"
        class="min-h-[32px] px-3 py-1 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer border"
        :class="selectedCategory === cat ? 'bg-theme-primary text-white font-bold border-theme-primary shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ cat }}
      </button>
      <button
        v-if="selectedCategory !== 'all'"
        type="button"
        @click="$emit('filter-category', 'all')"
        class="min-h-[32px] px-2.5 py-1 rounded-xl text-xs font-bold text-rose-500 dark:text-rose-400 bg-rose-500/10 border border-rose-500/30 transition-all whitespace-nowrap cursor-pointer"
      >
        {{ $t('expenses.clear_filter') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseInput from '../Form/BaseInput.vue';

defineProps({
  searchQuery: { type: String, default: '' },
  selectedCostCenter: { type: String, default: 'all' },
  selectedCategory: { type: String, default: 'all' },
  costCenterFilterOptions: { type: Array, default: () => [] },
  quickCategories: { type: Array, default: () => [] },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
});

defineEmits([
  'update:searchQuery',
  'update:selectedCostCenter',
  'update:dateFrom',
  'update:dateTo',
  'search',
  'filter',
  'filter-category',
]);
</script>
