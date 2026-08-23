<template>
  <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 no-print">
    <!-- Date Inputs -->
    <div class="flex items-center gap-2 flex-wrap">
      <div class="flex items-center gap-1.5">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('common.from') }}:</span>
        <input
          :value="dateFrom"
          @input="$emit('update:dateFrom', $event.target.value)"
          type="date"
          class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
      </div>

      <div class="flex items-center gap-1.5">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('common.to') }}:</span>
        <input
          :value="dateTo"
          @input="$emit('update:dateTo', $event.target.value)"
          type="date"
          class="h-9 px-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-theme-primary focus:outline-none"
        >
      </div>

      <BaseButton
        variant="primary"
        size="sm"
        @click="$emit('filter')"
        class="font-black shadow-theme-primary shadow-sm"
      >
        {{ $t('common.filter') }}
      </BaseButton>
    </div>

    <!-- Quick Date Range Pills -->
    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700 overflow-x-auto">
      <button
        type="button"
        @click="$emit('apply-preset', 'today')"
        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap min-h-[32px]"
        :class="activePreset === 'today' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('common.today') }}
      </button>

      <button
        type="button"
        @click="$emit('apply-preset', 'this_month')"
        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap min-h-[32px]"
        :class="activePreset === 'this_month' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('common.this_month') }}
      </button>

      <button
        type="button"
        @click="$emit('apply-preset', 'all')"
        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer whitespace-nowrap min-h-[32px]"
        :class="activePreset === 'all' ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
      >
        {{ $t('common.all') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import BaseButton from '../Common/BaseButton.vue';

defineProps({
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  activePreset: { type: String, default: 'all' },
});

defineEmits(['update:dateFrom', 'update:dateTo', 'filter', 'apply-preset']);
</script>
