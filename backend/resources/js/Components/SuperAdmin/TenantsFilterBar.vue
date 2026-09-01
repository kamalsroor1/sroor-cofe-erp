<template>
  <div class="p-4 bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md flex flex-col md:flex-row items-center justify-between gap-4 font-tajawal">
    <div class="flex-1 w-full">
      <BaseSearchInput
        :model-value="filters.search"
        @update:model-value="$emit('update:search', $event)"
        :placeholder="$t('super.search_tenants_placeholder')"
        :debounce="300"
      />
    </div>

    <div class="flex items-center gap-3 w-full md:w-auto">
      <div class="w-full md:w-44">
        <BaseSelect
          :model-value="filters.status"
          @update:model-value="$emit('update:status', $event)"
          :options="statusOptions"
        />
      </div>

      <div class="w-full md:w-44">
        <BaseSelect
          :model-value="filters.plan_id"
          @update:model-value="$emit('update:plan', $event)"
          :options="planOptions"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
  filters: { type: Object, default: () => ({ search: '', status: 'all', plan_id: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  planOptions: { type: Array, default: () => [] },
});

defineEmits(['update:search', 'update:status', 'update:plan']);
</script>
