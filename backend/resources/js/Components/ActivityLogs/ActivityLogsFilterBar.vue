<template>
  <div class="p-4 bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-3 font-tajawal">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
      <!-- Search -->
      <BaseSearchInput
        :model-value="search"
        @update:model-value="$emit('update:search', $event)"
        :placeholder="$t('activity.search_placeholder')"
      />

      <!-- Module -->
      <BaseSelect
        :model-value="module"
        @update:model-value="$emit('update:module', $event)"
        :options="moduleOptions"
        :searchable="false"
      />

      <!-- User -->
      <BaseSelect
        :model-value="userId"
        @update:model-value="$emit('update:user-id', $event)"
        :options="userOptions"
        :searchable="true"
      />

      <!-- Store -->
      <BaseSelect
        :model-value="storeId"
        @update:model-value="$emit('update:store-id', $event)"
        :options="storeOptions"
        :searchable="true"
      />
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';

defineProps({
  search: { type: String, default: '' },
  module: { type: String, default: 'all' },
  userId: { type: [String, Number], default: 'all' },
  storeId: { type: [String, Number], default: 'all' },
  moduleOptions: { type: Array, default: () => [] },
  userOptions: { type: Array, default: () => [] },
  storeOptions: { type: Array, default: () => [] },
});

defineEmits(['update:search', 'update:module', 'update:user-id', 'update:store-id']);
</script>
