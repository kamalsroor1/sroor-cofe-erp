<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800">
      <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.stores_comparison_sub') }}</h3>
    </div>

    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="6" :cols="8" />
    </div>

    <div v-else-if="storeBreakdown.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3 text-start font-bold">{{ $t('inventory.store_name') }}</th>
              <th class="p-3 text-center font-bold">{{ $t('invoices.invoices_count_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.total_sales_revenue') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('invoices.paid') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('invoices.remaining_due') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.gross_profit_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('dashboard.profit_margin_pct') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.market_share_pct') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
            <tr v-for="st in storeBreakdown" :key="st.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ st.name }}</td>
              <td class="p-3 text-center font-mono font-bold text-slate-700 dark:text-slate-300">{{ st.invoice_count }}</td>
              <td class="p-3 text-end font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(st.total_sales) }}</td>
              <td class="p-3 text-end font-mono text-emerald-500 dark:text-emerald-400">{{ formatMoney(st.total_paid) }}</td>
              <td class="p-3 text-end font-mono text-theme-primary">{{ formatMoney(st.total_remaining) }}</td>
              <td class="p-3 text-end font-mono font-black text-emerald-500 dark:text-emerald-400">{{ formatMoney(st.gross_profit) }}</td>
              <td class="p-3 text-end font-mono font-bold text-cyan-500 dark:text-cyan-400">{{ st.margin }}%</td>
              <td class="p-3 text-end font-mono font-black text-theme-primary">{{ st.share_pct }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="st in storeBreakdown"
          :key="st.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ st.name }}</h4>
            <span class="text-xs font-black font-mono text-theme-primary">{{ $t('reports.market_share_pct') }}: {{ st.share_pct }}%</span>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span class="text-slate-500">{{ $t('reports.total_sales_revenue') }}: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(st.total_sales) }}</span></span>
            <span class="font-mono font-black text-emerald-500">+{{ formatMoney(st.gross_profit) }}</span>
          </div>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('reports.no_data_title')"
      :description="$t('reports.no_data_desc')"
      icon="🏢"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  storeBreakdown: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>
