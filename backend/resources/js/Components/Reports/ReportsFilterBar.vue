<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-3 font-tajawal">
    <!-- Preset Periods & Store Selector -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-1.5">
        <button
          v-for="p in presets"
          :key="p.key"
          type="button"
          @click="$emit('set-period', p.key)"
          class="min-h-[36px] px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer active:scale-95"
          :class="period === p.key ? 'bg-theme-primary text-white font-black shadow-sm' : 'bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800'"
        >
          {{ p.label }}
        </button>
      </div>

      <!-- Store Selector Filter -->
      <div class="w-full sm:w-56">
        <select
          :value="storeId"
          @change="$emit('update:storeId', $event.target.value)"
          class="w-full h-10 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none cursor-pointer"
        >
          <option value="all">{{ $t('reports.all_stores_branches') }}</option>
          <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
    </div>

    <!-- Custom Dates & Stock Filter Row -->
    <div class="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-slate-200 dark:border-slate-800/80">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('common.from') }}:</span>
        <input
          :value="from"
          @input="$emit('update:from', $event.target.value)"
          @change="$emit('date-change')"
          type="date"
          class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none cursor-pointer"
        >
        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('common.to') }}:</span>
        <input
          :value="to"
          @input="$emit('update:to', $event.target.value)"
          @change="$emit('date-change')"
          type="date"
          class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none cursor-pointer"
        >
      </div>

      <div v-if="activeTab === 'inventory'" class="flex items-center gap-2">
        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $t('reports.stock_filter_label') }}:</span>
        <select
          :value="stockFilter"
          @change="$emit('update:stockFilter', $event.target.value)"
          class="h-9 px-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-theme-primary focus:outline-none cursor-pointer"
        >
          <option value="all">{{ $t('inventory.all_stock') }}</option>
          <option value="in_stock">{{ $t('reports.in_stock_only') }}</option>
          <option value="zero_stock">{{ $t('reports.zero_stock_only') }}</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  presets: { type: Array, default: () => [] },
  period: { type: String, default: 'this_month' },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  storeId: { type: [String, Number], default: 'all' },
  stockFilter: { type: String, default: 'all' },
  stores: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'sales' },
});

defineEmits(['set-period', 'update:storeId', 'update:from', 'update:to', 'update:stockFilter', 'date-change']);
</script>
