<template>
  <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 font-tajawal">
    <!-- Total Inflow -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{ $t('treasury.total_receipts_in') }}</span>
      <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
        +{{ formatMoney(summary.total_cash_in || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.inflow_details_sub') }}</span>
    </div>

    <!-- Total Outflow -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{ $t('treasury.total_disbursements_out') }}</span>
      <div class="text-xl font-black text-rose-500 dark:text-rose-400 font-mono">
        -{{ formatMoney(summary.total_cash_out || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.outflow_details_sub') }}</span>
    </div>

    <!-- Net Cash Today -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{ $t('treasury.net_cash_flow') }}</span>
      <div
        class="text-xl font-black font-mono"
        :class="(summary.net_cash_today || 0) >= 0 ? 'text-cyan-500 dark:text-cyan-400' : 'text-theme-primary'"
      >
        {{ (summary.net_cash_today || 0) > 0 ? '+' : '' }}{{ formatMoney(summary.net_cash_today || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.net_cash_flow_sub') }}</span>
    </div>

    <!-- Expected Cash In Drawer -->
    <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50/40 dark:from-slate-900 dark:to-slate-950 border border-theme-border shadow-sm dark:shadow-md space-y-1">
      <span class="text-[11px] font-bold text-theme-primary block">{{ $t('treasury.expected_drawer_balance') }}</span>
      <div class="text-xl font-black text-theme-primary font-mono">
        {{ formatMoney(summary.expected_cash_in_drawer || 0) }} <span class="text-xs text-theme-primary/80 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.expected_drawer_sub') }}</span>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  summary: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
