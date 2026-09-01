<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 font-tajawal">
    <template v-if="loading">
      <StatCardSkeleton v-for="i in 4" :key="i" />
    </template>
    <template v-else>
      <!-- Total Returns Value -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('returns.total_returns_value') }}</span>
          <TrendingDown class="w-4 h-4 text-rose-500 dark:text-rose-400" />
        </div>
        <div class="text-2xl font-black text-rose-500 dark:text-rose-400 font-mono">
          {{ formatMoney(summary.total_value || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
        </div>
        <span class="text-[10px] text-slate-400">{{ $t('returns.total_returns_value_sub') }}</span>
      </div>

      <!-- Sales Returns Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('returns.sales_returns_title') }}</span>
          <RotateCcw class="w-4 h-4 text-cyan-500 dark:text-cyan-400" />
        </div>
        <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400 font-mono">
          {{ summary.sales_count || 0 }} <span class="text-xs text-slate-400">{{ $t('returns.doc_unit') }}</span>
        </div>
        <span class="text-[10px] text-slate-400">{{ $t('returns.sales_returns_sub') }}</span>
      </div>

      <!-- Purchase Returns Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('returns.purchase_returns_title') }}</span>
          <RotateCw class="w-4 h-4 text-theme-primary" />
        </div>
        <div class="text-2xl font-black text-theme-primary font-mono">
          {{ summary.purchase_count || 0 }} <span class="text-xs text-slate-400">{{ $t('returns.doc_unit') }}</span>
        </div>
        <span class="text-[10px] text-slate-400">{{ $t('returns.purchase_returns_sub') }}</span>
      </div>

      <!-- Total Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('returns.total_documents') }}</span>
          <FileText class="w-4 h-4 text-slate-400" />
        </div>
        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
          {{ summary.total_count || 0 }} <span class="text-xs text-slate-400">{{ $t('returns.doc_unit') }}</span>
        </div>
        <span class="text-[10px] text-slate-400">{{ $t('returns.total_documents_sub') }}</span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { TrendingDown, RotateCcw, RotateCw, FileText } from 'lucide-vue-next';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

defineProps({
  summary: { type: Object, default: () => ({ total_value: 0, sales_count: 0, purchase_count: 0, total_count: 0 }) },
  loading: { type: Boolean, default: false },
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
