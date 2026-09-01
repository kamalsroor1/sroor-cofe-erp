<template>
  <div class="p-4 bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg font-tajawal">
    <BaseSearchInput
      :model-value="search"
      @update:model-value="$emit('update:search', $event)"
      :placeholder="$t('trash.search_trash_placeholder')"
    />
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';

defineProps({
  search: { type: String, default: '' },
});

defineEmits(['update:search']);
</script>
