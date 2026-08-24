<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 font-tajawal">
    <!-- Search Input -->
    <div class="flex-1">
      <BaseSearchInput
        :model-value="searchQuery"
        @update:model-value="$emit('update:searchQuery', $event)"
        :placeholder="$t('returns.search_returns_placeholder')"
        :debounce="300"
      />
    </div>

    <!-- Return Type Filter -->
    <div class="w-full md:w-56">
      <BaseSelect
        :model-value="selectedType"
        @update:model-value="$emit('update:selectedType', $event)"
        :options="typeOptions"
        :placeholder="$t('returns.all_return_types')"
        :searchable="false"
      />
    </div>

    <!-- Date Range Filter -->
    <div class="flex items-center gap-2">
      <div class="w-36">
        <BaseDatePicker
          :model-value="dateFrom"
          @update:model-value="$emit('update:dateFrom', $event)"
          :placeholder="$t('common.from')"
        />
      </div>
      <span class="text-xs text-slate-500 font-bold">—</span>
      <div class="w-36">
        <BaseDatePicker
          :model-value="dateTo"
          @update:model-value="$emit('update:dateTo', $event)"
          :placeholder="$t('common.to')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';

defineProps({
  searchQuery: { type: String, default: '' },
  selectedType: { type: String, default: 'all' },
  typeOptions: { type: Array, default: () => [] },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
});

defineEmits(['update:searchQuery', 'update:selectedType', 'update:dateFrom', 'update:dateTo']);
</script>
