<template>
  <div class="p-4 bg-white dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg flex flex-col md:flex-row items-center justify-between gap-4 font-tajawal">
    <div class="flex-1 w-full">
      <BaseSearchInput
        :model-value="search"
        @update:model-value="$emit('update:search', $event)"
        :placeholder="$t('users.search_users_placeholder')"
      />
    </div>

    <div class="flex items-center gap-3 w-full md:w-auto">
      <select
        :value="role"
        @change="$emit('update:role', $event.target.value)"
        class="w-full md:w-48 h-10 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-300 focus:outline-none focus:border-theme-primary font-tajawal cursor-pointer"
      >
        <option value="all">{{ $t('users.all_roles_filter') }}</option>
        <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import BaseSearchInput from '../Form/BaseSearchInput.vue';

defineProps({
  search: { type: String, default: '' },
  role: { type: String, default: 'all' },
  rolesList: { type: Array, default: () => [] },
});

defineEmits(['update:search', 'update:role']);
</script>
