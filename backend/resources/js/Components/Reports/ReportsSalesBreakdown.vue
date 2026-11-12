<template>
  <section
    class="rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md p-4 sm:p-5 space-y-3 font-tajawal"
  >
    <h3 class="text-xs font-black text-slate-700 dark:text-slate-300">{{ $t('reports.sales_summary_title') }}</h3>

    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr_auto_1fr] items-stretch gap-2 sm:gap-3">
      <!-- Gross sales -->
      <div class="space-y-1 min-w-0">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.gross_sales_label') }}</span>
        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono break-words">
          {{ formatMoney(grossSales) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="block text-[10px] text-slate-500 dark:text-slate-400">
          {{ $t('reports.gross_sales_hint') }} -
          {{ $t('reports.invoices_count_label', { count: summary.invoices_count || 0 }) }}
        </span>
      </div>

      <span
        class="flex items-center justify-center text-xl font-black text-slate-400 dark:text-slate-500"
        aria-hidden="true"
        >−</span
      >

      <!-- Returns -->
      <div class="space-y-1 min-w-0">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.returns_label') }}</span>
        <div
          class="text-2xl font-black font-mono break-words"
          :class="hasReturns ? 'text-rose-500 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500'"
        >
          {{ formatMoney(summary.total_returns || 0) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="block text-[10px] text-slate-500 dark:text-slate-400">{{
          hasReturns
            ? $t('reports.returns_count_hint', { count: summary.returns_count || 0 })
            : $t('reports.returns_none_hint')
        }}</span>
      </div>

      <span
        class="flex items-center justify-center text-xl font-black text-slate-400 dark:text-slate-500"
        aria-hidden="true"
        >=</span
      >

      <!-- Net sales -->
      <div
        class="space-y-1 min-w-0 rounded-xl border p-3 -my-1"
        style="background-color: var(--color-primary-light); border-color: var(--color-primary-border)"
      >
        <span class="text-xs font-black text-theme-primary">{{ $t('reports.net_sales_label') }}</span>
        <div class="text-2xl font-black text-theme-primary font-mono break-words">
          {{ formatMoney(summary.net_sales ?? summary.total_sales) }}
          <span class="text-xs font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span class="block text-[10px] text-slate-600 dark:text-slate-400">{{ $t('reports.net_sales_hint') }}</span>
      </div>
    </div>

    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $t('reports.profit_on_net_note') }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useReturnsDisplay } from '../../Composables/useReturnsDisplay';

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
});

const { formatMoney, currencyLabel } = useFormatters();
const { hasAmount } = useReturnsDisplay();

const grossSales = computed(() => props.summary.gross_sales ?? props.summary.total_sales);
const hasReturns = computed(() => hasAmount(props.summary.total_returns));
</script>
