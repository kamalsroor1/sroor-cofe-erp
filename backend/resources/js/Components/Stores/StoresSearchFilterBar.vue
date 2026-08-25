<template>
  <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-xs space-y-3 font-tajawal">
    <div class="flex flex-col sm:flex-row items-center gap-3">
      <!-- Search Input -->
      <div class="flex-1 w-full">
        <BaseSearchInput
          :model-value="search"
          @update:model-value="$emit('update:search', $event)"
          :placeholder="$t('inventory.search_stores_placeholder')"
        />
      </div>

      <!-- Type & Status Filters -->
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <!-- Store Type Filter -->
        <BaseSelect
          :model-value="type"
          @update:model-value="$emit('update:type', $event)"
          :options="typeOptions"
          wrapper-class="min-w-[140px]"
          :searchable="false"
        />

        <!-- Status Filter -->
        <BaseSelect
          :model-value="status"
          @update:model-value="$emit('update:status', $event)"
          :options="statusOptions"
          wrapper-class="min-w-[130px]"
          :searchable="false"
        />

        <!-- Clear Filters Button -->
        <BaseButton
          v-if="hasActiveFilters"
          type="button"
          variant="secondary"
          size="md"
          @click="$emit('reset')"
          class="shrink-0"
          :title="$t('common.reset_filters')"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span class="hidden md:inline">{{ $t('common.reset_filters') }}</span>
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { RotateCcw } from 'lucide-vue-next';
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseButton from '../Common/BaseButton.vue';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();

defineProps({
  search: { type: String, default: '' },
  type: { type: String, default: 'all' },
  status: { type: String, default: 'all' },
  hasActiveFilters: { type: Boolean, default: false },
});

defineEmits(['update:search', 'update:type', 'update:status', 'reset']);

const typeOptions = [
  { value: 'all', label: t('inventory.all_store_types') },
  { value: 'retail_shop', label: t('inventory.retail_shop') },
  { value: 'warehouse', label: t('inventory.warehouse') },
  { value: 'van', label: t('inventory.distribution_van') },
];

const statusOptions = [
  { value: 'all', label: t('common.all') },
  { value: 'active', label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
];
</script>
