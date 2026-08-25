<template>
  <div class="p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
    <!-- Fast Search Input -->
    <div class="flex-1 min-w-[240px]">
      <BaseSearchInput
        :model-value="searchQuery"
        :placeholder="$t('inventory.search_item_placeholder')"
        @update:model-value="$emit('update:searchQuery', $event); $emit('search')"
        @clear="$emit('update:searchQuery', ''); $emit('search')"
      />
    </div>

    <!-- Category Dropdown Select -->
    <div class="w-full lg:w-56">
      <BaseSelect
        :model-value="selectedCategory"
        :options="categoryOptions"
        :placeholder="$t('inventory.all_categories')"
        :searchable="false"
        @update:model-value="$emit('update:selectedCategory', $event); $emit('search')"
      />
    </div>

    <!-- Stock Status Filter Pills -->
    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-950/80 p-1 rounded-xl border border-slate-200 dark:border-slate-800 overflow-x-auto scrollbar-none shrink-0">
      <button
        type="button"
        @click="$emit('update:stockStatus', 'all'); $emit('search')"
        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="stockStatus === 'all'
          ? 'bg-theme-primary text-slate-950 font-black shadow-xs'
          : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
      >
        {{ $t('common.all') }}
      </button>

      <button
        type="button"
        @click="$emit('update:stockStatus', 'low'); $emit('search')"
        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="stockStatus === 'low'
          ? 'bg-rose-500/20 text-rose-500 border border-rose-500/40 font-black shadow-xs'
          : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
      >
        🚨 {{ $t('inventory.low_stock_only') }}
      </button>

      <button
        type="button"
        @click="$emit('update:stockStatus', 'out'); $emit('search')"
        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="stockStatus === 'out'
          ? 'bg-theme-light text-theme-primary border border-theme-border font-black shadow-xs'
          : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
      >
        ❌ {{ $t('inventory.out_of_stock_only') }}
      </button>

      <button
        type="button"
        @click="$emit('update:stockStatus', 'in_stock'); $emit('search')"
        class="min-h-[36px] px-3 py-1.5 rounded-lg text-xs font-bold font-tajawal transition-all whitespace-nowrap cursor-pointer"
        :class="stockStatus === 'in_stock'
          ? 'bg-emerald-500/20 text-emerald-500 border border-emerald-500/40 font-black shadow-xs'
          : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
      >
        ✅ {{ $t('inventory.available_only') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseSearchInput from '../Form/BaseSearchInput.vue';
import BaseSelect from '../Form/BaseSelect.vue';
import { trans } from '../../helpers/trans';

const props = defineProps({
  searchQuery: { type: String, default: '' },
  selectedCategory: { type: String, default: 'all' },
  stockStatus: { type: String, default: 'all' },
  categories: { type: Array, default: () => [] },
});

defineEmits(['update:searchQuery', 'update:selectedCategory', 'update:stockStatus', 'search']);

const categoryOptions = computed(() => {
  const allOption = { value: 'all', label: trans('inventory.all_categories') };
  const dynamicOptions = props.categories.map((c) => ({
    value: typeof c === 'object' ? c.name : c,
    label: typeof c === 'object' ? `${c.icon || '☕'} ${c.name}` : c,
  }));
  return [allOption, ...dynamicOptions];
});
</script>
