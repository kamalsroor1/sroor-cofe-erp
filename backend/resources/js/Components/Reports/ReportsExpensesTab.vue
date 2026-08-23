<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800">
      <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.expenses_breakdown_by_cat') }}</h3>
    </div>

    <div v-if="loading" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <StatCardSkeleton v-for="i in 6" :key="i" />
    </div>

    <div v-else-if="expensesBreakdown.length > 0" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="e in expensesBreakdown"
        :key="e.category"
        class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 space-y-2"
      >
        <div class="flex items-center justify-between text-xs font-bold">
          <span class="text-theme-primary font-tajawal">{{ e.category }}</span>
          <span class="text-slate-500 dark:text-slate-400 font-mono">{{ $t('reports.vouchers_count', { count: e.count }) }}</span>
        </div>
        <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
          {{ formatMoney(e.amount) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('reports.no_data_title')"
      :description="$t('reports.no_data_desc')"
      icon="💸"
    />
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  expensesBreakdown: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>
