<template>
  <div v-if="loading" class="space-y-6">
    <div
      class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs p-4 sm:p-5 space-y-3"
    >
      <Skeleton width="w-28" height="h-3.5" rounded="rounded-md" />
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div v-for="i in 3" :key="i" class="space-y-2">
          <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
          <Skeleton width="w-32" height="h-8" rounded="rounded-lg" />
          <Skeleton width="w-24" height="h-2.5" rounded="rounded-md" />
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCardSkeleton v-for="i in 6" :key="i" />
    </div>
  </div>

  <div v-else class="space-y-6 font-tajawal">
    <ReportsSalesBreakdown :summary="summary" />

    <!-- Financial Metrics Cards Grid (cost, profit and margin are on net sales) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total COGS -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span
          class="text-xs font-bold text-slate-500 dark:text-slate-400 cursor-help"
          :title="$t('reports.cogs_tooltip')"
          >{{ $t('reports.total_cogs_label') }}</span
        >
        <div class="text-2xl font-black text-rose-500 dark:text-rose-400 font-mono">
          {{ formatMoney(summary.total_cogs) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t('reports.total_cogs_desc') }}</span>
      </div>

      <!-- Gross Profit & Margin -->
      <div
        class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-slate-900/90 border border-emerald-200 dark:border-emerald-500/30 shadow-sm dark:shadow-md space-y-1"
      >
        <span
          class="text-xs font-bold text-emerald-600 dark:text-emerald-400 cursor-help"
          :title="$t('reports.gross_profit_tooltip')"
          >{{ $t('reports.gross_profit_label') }}</span
        >
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          {{ formatMoney(summary.gross_profit) }}
          <span class="text-xs text-emerald-500 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[10px] text-emerald-500 font-bold">{{
          $t('reports.gross_margin_label', { pct: summary.margin_percentage || 0 })
        }}</span>
      </div>

      <!-- Operating Expenses -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.operating_expenses_label')
        }}</span>
        <div class="text-2xl font-black text-theme-primary font-mono">
          {{ formatMoney(summary.total_expenses) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[10px] text-slate-500 dark:text-slate-400">{{
          $t('reports.expenses_count_label', { count: summary.expenses_count || 0 })
        }}</span>
      </div>

      <!-- Cash Collected -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.cash_collected_treasury')
        }}</span>
        <div class="text-xl font-black text-cyan-500 dark:text-cyan-400 font-mono">
          {{ formatMoney(summary.total_paid) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ $t('reports.cash_collected_sub') }}</span>
      </div>

      <!-- Net True Profit -->
      <div
        class="sm:col-span-2 lg:col-span-2 p-5 rounded-2xl bg-gradient-to-r from-emerald-50 via-teal-50 to-slate-50 dark:from-emerald-950/60 dark:via-teal-950/50 dark:to-slate-950/80 border border-emerald-200 dark:border-emerald-500/40 shadow-sm dark:shadow-xl space-y-1"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-black text-emerald-700 dark:text-emerald-300">{{
            $t('reports.net_true_profit_label')
          }}</span>
          <span
            class="px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-mono font-bold text-[10px] border border-emerald-200 dark:border-emerald-500/30"
            >{{ $t('reports.formula_badge') }}</span
          >
        </div>
        <div
          class="text-3xl font-black font-mono"
          :class="
            (summary.net_profit || 0) >= 0
              ? 'text-emerald-500 dark:text-emerald-400'
              : 'text-rose-500 dark:text-rose-400'
          "
        >
          {{ formatMoney(summary.net_profit) }}
          <span class="text-sm font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[11px] text-slate-600 dark:text-slate-400">{{ $t('reports.net_profit_desc') }}</span>
      </div>

      <!-- Receivables in Period -->
      <div
        class="sm:col-span-2 lg:col-span-2 p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.remaining_receivables_period')
        }}</span>
        <div class="text-xl font-black text-theme-primary font-mono">
          {{ formatMoney(summary.total_remaining) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="text-[10px] text-slate-500 dark:text-slate-400">{{
          $t('reports.total_customers_debt_sub', { amount: formatMoney(summary.total_customers_debt || 0) })
        }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import ReportsSalesBreakdown from './ReportsSalesBreakdown.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  summary: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
