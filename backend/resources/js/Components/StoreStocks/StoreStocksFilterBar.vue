<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
    <!-- Store Selector Dropdown -->
    <div class="w-full md:w-64">
      <BaseSelect
        :model-value="selectedStoreId"
        @update:model-value="$emit('update:selectedStoreId', Number($event)); $emit('store-change')"
        :label="$t('inventory.store')"
        :options="storeOptions"
      />
    </div>

    <!-- Search & Status Filters -->
    <div class="flex flex-col sm:flex-row items-center gap-2 flex-1 md:justify-end">
      <!-- Search Input -->
      <div class="w-full sm:w-64">
        <BaseSearchInput
          :model-value="searchQuery"
          @update:model-value="$emit('update:searchQuery', $event)"
          @input="$emit('search')"
          :placeholder="$t('inventory.search_item_code')"
        />
      </div>

      <!-- Stock Status Filter -->
      <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700 w-full sm:w-auto">
        <button
          type="button"
          @click="$emit('set-status', 'all')"
          class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer min-h-[36px]"
          :class="stockStatus === 'all' ? 'bg-theme-primary text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          {{ $t('common.all') }}
        </button>

        <button
          type="button"
          @click="$emit('set-status', 'low')"
          class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer min-h-[36px]"
          :class="stockStatus === 'low' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          ⚠️ {{ $t('inventory.low_stock') }}
        </button>

        <button
          type="button"
          @click="$emit('set-status', 'out')"
          class="flex-1 sm:flex-none px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer min-h-[36px]"
          :class="stockStatus === 'out' ? 'bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          🚨 {{ $t('inventory.out_of_stock') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import BaseSelect from '../Form/BaseSelect.vue';
import BaseSearchInput from '../Form/BaseSearchInput.vue';

defineProps({
  selectedStoreId: { type: Number, default: null },
  storeOptions: { type: Array, default: () => [] },
  searchQuery: { type: String, default: '' },
  stockStatus: { type: String, default: 'all' },
});

defineEmits([
  'update:selectedStoreId',
  'update:searchQuery',
  'store-change',
  'search',
  'set-status',
]);
</script>
