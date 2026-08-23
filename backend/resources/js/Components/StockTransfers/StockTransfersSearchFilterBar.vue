<template>
  <div class="p-4 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 font-tajawal">
    <!-- Search Bar -->
    <div class="flex-1 min-w-[200px]">
      <BaseSearchInput
        :model-value="search"
        @update:model-value="$emit('update:search', $event)"
        :placeholder="$t('inventory.search_transfers_placeholder') || 'بحث برقم إذن التحويل أو الملاحظات...'"
        :debounce="300"
      />
    </div>

    <!-- Filters Group -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-center">
      <!-- From Store Filter -->
      <BaseSelect
        :model-value="fromStoreId"
        @update:model-value="$emit('update:fromStoreId', $event)"
        :options="fromStoreOptions"
        :placeholder="$t('inventory.all_from_stores') || 'من مخزن (الكل)'"
      />

      <!-- To Store Filter -->
      <BaseSelect
        :model-value="toStoreId"
        @update:model-value="$emit('update:toStoreId', $event)"
        :options="toStoreOptions"
        :placeholder="$t('inventory.all_to_stores') || 'إلى مخزن (الكل)'"
      />

      <!-- Date Range (BaseDatePicker) -->
      <div class="flex items-center gap-1.5">
        <BaseDatePicker
          :model-value="dateFrom"
          @update:model-value="$emit('update:dateFrom', $event)"
          :placeholder="$t('invoices.from_date') || 'من تاريخ'"
        />
        <span class="text-xs text-slate-400 font-bold">—</span>
        <BaseDatePicker
          :model-value="dateTo"
          @update:model-value="$emit('update:dateTo', $event)"
          :placeholder="$t('invoices.to_date') || 'إلى تاريخ'"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import BaseDatePicker from '../Form/BaseDatePicker.vue';
import { trans } from '../../helpers/trans';

const props = defineProps({
  search: { type: String, default: '' },
  fromStoreId: { type: [String, Number], default: 'all' },
  toStoreId: { type: [String, Number], default: 'all' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  stores: { type: Array, default: () => [] },
});

defineEmits([
  'update:search',
  'update:fromStoreId',
  'update:toStoreId',
  'update:dateFrom',
  'update:dateTo',
]);

const fromStoreOptions = computed(() => [
  { value: 'all', label: trans('inventory.all_from_stores') || 'من مخزن (الكل)' },
  ...props.stores.map((s) => ({ value: s.id, label: s.name })),
]);

const toStoreOptions = computed(() => [
  { value: 'all', label: trans('inventory.all_to_stores') || 'إلى مخزن (الكل)' },
  ...props.stores.map((s) => ({ value: s.id, label: s.name })),
]);
</script>
