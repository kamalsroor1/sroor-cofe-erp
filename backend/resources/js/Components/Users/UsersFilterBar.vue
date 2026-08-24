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
      <BaseSelect
        :model-value="role"
        @update:model-value="$emit('update:role', $event)"
        :options="formattedRoles"
        wrapper-class="w-full md:w-48"
        :searchable="false"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

const props = defineProps({
  search: { type: String, default: '' },
  role: { type: String, default: 'all' },
  rolesList: { type: Array, default: () => [] },
});

defineEmits(['update:search', 'update:role']);

const formattedRoles = computed(() => [
  { value: 'all', label: t('users.all_roles_filter') },
  ...props.rolesList.map(r => ({ value: r.id, label: r.name }))
]);
</script>
