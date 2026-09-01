<template>
  <div class="space-y-6 font-tajawal">
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCardSkeleton v-for="i in 3" :key="i" />
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total Inflow -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.total_inflow_label') }}</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          +{{ formatMoney(treasuryData.total_inflow || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
      </div>

      <!-- Total Outflow -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.total_outflow_label') }}</span>
        <div class="text-2xl font-black text-rose-500 dark:text-rose-400 font-mono">
          -{{ formatMoney(treasuryData.total_outflow || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
      </div>

      <!-- Net Cash Flow -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.net_cash_flow_label') }}</span>
        <div class="text-2xl font-black font-mono" :class="(treasuryData.net_cash_flow || 0) >= 0 ? 'text-cyan-500 dark:text-cyan-400' : 'text-theme-primary'">
          {{ (treasuryData.net_cash_flow || 0) > 0 ? '+' : '' }}{{ formatMoney(treasuryData.net_cash_flow || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  treasuryData: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
