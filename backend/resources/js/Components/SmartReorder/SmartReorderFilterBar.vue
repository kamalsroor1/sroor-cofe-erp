<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <!-- Search Input -->
    <div class="flex-1">
      <BaseSearchInput
        :model-value="searchQuery"
        @update:model-value="$emit('update:searchQuery', $event)"
        @input="$emit('search')"
        :placeholder="$t('purchases.search_item_material')"
      />
    </div>

    <!-- Analysis Days Selector -->
    <div class="w-full md:w-48">
      <BaseSelect
        :model-value="analysisDays"
        @update:model-value="$emit('update:analysisDays', Number($event)); $emit('refresh')"
        :options="analysisDaysOptions"
      />
    </div>

    <!-- Target Cover Days Selector -->
    <div class="w-full md:w-48">
      <BaseSelect
        :model-value="targetCoverDays"
        @update:model-value="$emit('update:targetCoverDays', Number($event)); $emit('refresh')"
        :options="targetCoverOptions"
      />
    </div>

    <!-- Urgency / Risk Level Selector -->
    <div class="w-full md:w-40">
      <BaseSelect
        :model-value="selectedUrgency"
        @update:model-value="$emit('update:selectedUrgency', $event); $emit('refresh')"
        :options="urgencyOptions"
      />
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
  searchQuery: { type: String, default: '' },
  analysisDays: { type: Number, default: 14 },
  targetCoverDays: { type: Number, default: 15 },
  selectedUrgency: { type: String, default: 'all' },
  analysisDaysOptions: { type: Array, default: () => [] },
  targetCoverOptions: { type: Array, default: () => [] },
  urgencyOptions: { type: Array, default: () => [] },
});

defineEmits([
  'update:searchQuery',
  'update:analysisDays',
  'update:targetCoverDays',
  'update:selectedUrgency',
  'search',
  'refresh',
]);
</script>
