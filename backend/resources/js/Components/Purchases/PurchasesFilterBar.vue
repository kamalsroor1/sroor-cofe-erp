<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <!-- Search Input -->
    <div class="flex-1">
      <BaseSearchInput
        :model-value="searchQuery"
        @update:model-value="$emit('update:searchQuery', $event)"
        @input="$emit('search')"
        :placeholder="$t('purchases.search_purchases_placeholder')"
      />
    </div>

    <!-- Status Filter Dropdown -->
    <div class="w-full md:w-48">
      <BaseSelect
        :model-value="selectedStatus"
        @update:model-value="$emit('update:selectedStatus', $event); $emit('filter')"
        :options="statusOptions"
      />
    </div>

    <!-- Date Range Filter -->
    <div class="flex items-center gap-2">
      <input
        :value="dateFrom"
        @input="$emit('update:dateFrom', $event.target.value)"
        @change="$emit('filter')"
        type="date"
        class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
      >
      <span class="text-xs text-slate-400 font-bold">—</span>
      <input
        :value="dateTo"
        @input="$emit('update:dateTo', $event.target.value)"
        @change="$emit('filter')"
        type="date"
        class="h-10 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
      >
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
  searchQuery: { type: String, default: '' },
  selectedStatus: { type: String, default: 'all' },
  statusOptions: { type: Array, default: () => [] },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
});

defineEmits([
  'update:searchQuery',
  'update:selectedStatus',
  'update:dateFrom',
  'update:dateTo',
  'search',
  'filter',
]);
</script>
