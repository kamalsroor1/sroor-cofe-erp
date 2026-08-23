<template>
  <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-xs space-y-3 font-tajawal">
    <div class="flex flex-col sm:flex-row items-center gap-3">
      <!-- 🔍 Search Input -->
      <div class="relative flex-1 w-full">
        <Search class="w-4 h-4 text-slate-400 absolute top-1/2 -translate-y-1/2 end-3.5 pointer-events-none" />
        <input
          :value="search"
          @input="$emit('update:search', $event.target.value)"
          type="text"
          :placeholder="$t('inventory.search_stores_placeholder')"
          class="w-full h-11 ps-4 pe-10 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary/20 focus:border-theme-primary transition-all font-tajawal"
        />
        <button
          v-if="search"
          type="button"
          @click="$emit('update:search', '')"
          class="absolute top-1/2 -translate-y-1/2 start-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer p-1"
        >
          ✕
        </button>
      </div>

      <!-- 🏷️ Type & Status Filters -->
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <!-- Store Type Filter -->
        <select
          :value="type"
          @change="$emit('update:type', $event.target.value)"
          class="h-11 px-3 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-theme-primary/20 focus:border-theme-primary font-tajawal cursor-pointer flex-1 sm:flex-initial min-w-[130px]"
        >
          <option value="all">{{ $t('inventory.all_store_types') }}</option>
          <option value="retail_shop">🏬 {{ $t('inventory.retail_shop') }}</option>
          <option value="warehouse">🏭 {{ $t('inventory.warehouse') }}</option>
          <option value="van">🚚 {{ $t('inventory.distribution_van') }}</option>
        </select>

        <!-- Status Filter -->
        <select
          :value="status"
          @change="$emit('update:status', $event.target.value)"
          class="h-11 px-3 bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-theme-primary/20 focus:border-theme-primary font-tajawal cursor-pointer flex-1 sm:flex-initial min-w-[120px]"
        >
          <option value="all">{{ $t('common.all') }}</option>
          <option value="active">✅ {{ $t('common.active') }}</option>
          <option value="inactive">🚫 {{ $t('common.inactive') }}</option>
        </select>

        <!-- Clear Filters Button -->
        <button
          v-if="hasActiveFilters"
          type="button"
          @click="$emit('reset')"
          class="h-11 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-2xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
          :title="$t('common.reset_filters')"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span class="hidden md:inline">{{ $t('common.reset_filters') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Search, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
  search: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'all',
  },
  status: {
    type: String,
    default: 'all',
  },
});

defineEmits(['update:search', 'update:type', 'update:status', 'reset']);

const hasActiveFilters = computed(() => {
  return props.search !== '' || props.type !== 'all' || props.status !== 'all';
});
</script>
