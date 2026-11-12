<template>
  <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-2 gap-3.5 font-tajawal" :class="showExpected ? 'sm:grid-cols-4' : 'sm:grid-cols-3'">
    <!-- Total Inflow -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{
        $t('treasury.total_receipts_in')
      }}</span>
      <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
        {{ totalInflow > 0 ? '+' : '' }}{{ formatMoney(totalInflow) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.inflow_details_sub') }}</span>
    </div>

    <!-- Total Outflow -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{
        $t('treasury.total_disbursements_out')
      }}</span>
      <div class="text-xl font-black text-rose-500 dark:text-rose-400 font-mono">
        {{ totalOutflow > 0 ? '-' : '' }}{{ formatMoney(totalOutflow) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.outflow_details_sub') }}</span>
      <span
        v-if="Number(summary.cash_refunds || 0) > 0"
        class="text-[10px] text-slate-500 dark:text-slate-400 block"
        data-testid="journal-cash-refunds"
      >
        {{ $t('treasury.cash_refunds_line') }}: {{ formatMoney(summary.cash_refunds) }} {{ currencyLabel }}
      </span>
    </div>

    <!-- Net Cash Today -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">{{
        $t('treasury.net_cash_today_label')
      }}</span>
      <div class="text-xl font-black font-mono" :class="netCashClass">
        {{ netCashDisplay }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ $t('treasury.net_cash_flow_sub') }}</span>
    </div>

    <!-- Expected Cash In Drawer (null for a cashier without reports.view: card not drawn) -->
    <div
      v-if="showExpected"
      data-testid="journal-expected-cash"
      class="p-4 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50/40 dark:from-slate-900 dark:to-slate-950 border border-theme-border shadow-sm dark:shadow-md space-y-1"
    >
      <span class="text-[11px] font-bold text-theme-primary block">{{ $t('treasury.expected_drawer_balance') }}</span>
      <div class="text-xl font-black text-theme-primary font-mono">
        {{ formatMoney(summary.expected_cash_in_drawer) }}
        <span class="text-xs text-theme-primary/80 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{
        $t('treasury.expected_drawer_open_shift_sub')
      }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { hasMetric } from '../../helpers/dashboardMetrics';

const { formatMoney, currencyLabel } = useFormatters();

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

const showExpected = computed(() => hasMetric(props.summary.expected_cash_in_drawer));

const totalInflow = computed(() => {
  const val = Number(props.summary.total_cash_in || 0);
  return Math.abs(val) < 0.0005 ? 0 : val;
});

const totalOutflow = computed(() => {
  const val = Number(props.summary.total_cash_out || 0);
  return Math.abs(val) < 0.0005 ? 0 : val;
});

const netCash = computed(() => {
  const val = Number(props.summary.net_cash_today || 0);
  return Math.abs(val) < 0.0005 ? 0 : val;
});

const netCashClass = computed(() => {
  if (netCash.value > 0) return 'text-emerald-500 dark:text-emerald-400';
  if (netCash.value < 0) return 'text-rose-500 dark:text-rose-400';
  return 'text-slate-700 dark:text-slate-300';
});

const netCashDisplay = computed(() => {
  if (netCash.value > 0) return `+${formatMoney(netCash.value)}`;
  if (netCash.value < 0) return formatMoney(netCash.value);
  return formatMoney(0);
});
</script>
