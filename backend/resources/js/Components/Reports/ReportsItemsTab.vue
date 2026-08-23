<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.items_profitability_title') }}</h3>
      <span class="text-xs text-slate-500 font-mono">{{ $t('reports.items_count_badge', { count: itemProfits.length }) }}</span>
    </div>

    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <div v-else-if="itemProfits.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.category') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.sold_quantity') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.total_sales_revenue') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.total_cogs_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.gross_profit_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('dashboard.profit_margin_pct') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
            <tr v-for="it in itemProfits" :key="it.item_id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.name }}</td>
              <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ it.code || '—' }}</td>
              <td class="p-3 text-slate-600 dark:text-slate-400 font-tajawal">{{ it.category }}</td>
              <td class="p-3 text-end font-mono font-bold text-cyan-600 dark:text-cyan-400">{{ it.total_qty }} {{ it.unit }}</td>
              <td class="p-3 text-end font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(it.total_revenue) }}</td>
              <td class="p-3 text-end font-mono text-rose-500 dark:text-rose-400">{{ formatMoney(it.total_cogs) }}</td>
              <td class="p-3 text-end font-mono font-black text-emerald-500 dark:text-emerald-400">{{ formatMoney(it.profit) }}</td>
              <td class="p-3 text-end font-mono font-bold text-theme-primary">{{ it.margin }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="it in itemProfits"
          :key="it.item_id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <div>
              <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ it.name }}</h4>
              <span class="text-xs text-slate-500">{{ it.category }} | <span class="font-mono">{{ it.code || '—' }}</span></span>
            </div>
            <div class="text-end">
              <span class="text-xs font-mono font-bold text-cyan-500">{{ it.total_qty }} {{ it.unit }}</span>
              <span class="text-sm font-black font-mono text-emerald-500 block">+{{ formatMoney(it.profit) }}</span>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span class="text-slate-500">{{ $t('reports.total_sales_revenue') }}: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(it.total_revenue) }}</span></span>
            <span class="font-mono font-bold text-theme-primary">{{ $t('dashboard.profit_margin_pct') }}: {{ it.margin }}%</span>
          </div>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('reports.no_data_title')"
      :description="$t('reports.no_data_desc')"
      icon="☕"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  itemProfits: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>
