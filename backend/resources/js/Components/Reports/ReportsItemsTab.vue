<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal"
  >
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">
        {{ $t('reports.items_profitability_title') }}
      </h3>
      <span v-if="!loading" data-testid="items-count-badge" class="text-xs text-slate-500 font-mono">{{
        $t('reports.items_showing_badge', { shown: itemProfits.length, total })
      }}</span>
    </div>

    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <ErrorState
      v-else-if="error && itemProfits.length === 0"
      data-testid="items-error"
      :message="errorMessage"
      @retry="$emit('retry')"
    />

    <div v-else-if="itemProfits.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr
              class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800"
            >
              <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('inventory.category') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.sold_quantity') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.net_sales_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.total_cogs_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.gross_profit_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('dashboard.profit_margin_pct') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
            <tr
              v-for="it in itemProfits"
              :key="it.item_id"
              class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors"
            >
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ it.name }}</td>
              <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ it.code || '—' }}</td>
              <td class="p-3 text-slate-600 dark:text-slate-400 font-tajawal">{{ it.category }}</td>
              <td class="p-3 text-end">
                <ReportsNetValue
                  :value="`${formatQty(it.total_qty)} ${it.unit}`"
                  :detail="qtyDetail(it.returned_qty, it.unit)"
                  value-class="font-mono font-bold text-cyan-600 dark:text-cyan-400"
                />
              </td>
              <td class="p-3 text-end">
                <ReportsNetValue
                  :value="formatMoney(it.total_revenue)"
                  :detail="moneyDetail(it.gross_revenue, it.returned_value)"
                />
              </td>
              <td class="p-3 text-end font-mono text-rose-500 dark:text-rose-400">{{ formatMoney(it.total_cogs) }}</td>
              <td class="p-3 text-end font-mono font-black text-emerald-500 dark:text-emerald-400">
                {{ formatMoney(it.profit) }}
              </td>
              <td class="p-3 text-end font-mono font-bold text-theme-primary">{{ it.margin }}%</td>
            </tr>
          </tbody>
          <tfoot v-if="totals">
            <tr
              data-testid="items-totals-row"
              class="bg-slate-100/90 dark:bg-slate-900 border-t-2 border-slate-300 dark:border-slate-700 font-black"
            >
              <td class="p-3 text-start" colspan="3">{{ $t('reports.totals_row_label') }}</td>
              <td class="p-3 text-end">
                <ReportsNetValue
                  :value="formatQty(totals.total_qty)"
                  :detail="qtyDetail(totals.returned_qty)"
                  value-class="font-mono font-bold text-cyan-600 dark:text-cyan-400"
                />
              </td>
              <td class="p-3 text-end">
                <ReportsNetValue
                  :value="formatMoney(totals.total_revenue)"
                  :detail="moneyDetail(totals.gross_revenue, totals.returned_value)"
                />
              </td>
              <td class="p-3 text-end font-mono text-rose-500 dark:text-rose-400">
                {{ formatMoney(totals.total_cogs) }}
              </td>
              <td class="p-3 text-end font-mono text-emerald-500 dark:text-emerald-400">
                {{ formatMoney(totals.profit) }}
              </td>
              <td class="p-3 text-end font-mono text-theme-primary">{{ totals.margin }}%</td>
            </tr>
          </tfoot>
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
              <span class="text-xs text-slate-500"
                >{{ it.category }} | <span class="font-mono">{{ it.code || '—' }}</span></span
              >
            </div>
            <div class="text-end">
              <ReportsNetValue
                :value="`${formatQty(it.total_qty)} ${it.unit}`"
                :detail="qtyDetail(it.returned_qty, it.unit)"
                value-class="text-xs font-mono font-bold text-cyan-500"
              />
              <span class="text-sm font-black font-mono text-emerald-500 block">+{{ formatMoney(it.profit) }}</span>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span class="text-slate-500"
              >{{ $t('reports.net_sales_label') }}:
              <ReportsNetValue
                class="inline-block align-top"
                :value="formatMoney(it.total_revenue)"
                :detail="moneyDetail(it.gross_revenue, it.returned_value)"
              />
            </span>
            <span class="font-mono font-bold text-theme-primary"
              >{{ $t('dashboard.profit_margin_pct') }}: {{ it.margin }}%</span
            >
          </div>
        </div>
        <div
          v-if="totals"
          data-testid="items-totals-card"
          class="p-4 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-slate-100/80 dark:bg-slate-900 text-xs space-y-1"
        >
          <h4 class="font-black text-slate-900 dark:text-white">{{ $t('reports.totals_row_label') }}</h4>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">{{ $t('reports.net_sales_label') }}</span>
            <span class="font-mono font-bold">{{ formatMoney(totals.total_revenue) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">{{ $t('reports.gross_profit_label') }}</span>
            <span class="font-mono font-black text-emerald-500">{{ formatMoney(totals.profit) }}</span>
          </div>
        </div>
      </div>

      <Pagination
        :pagination="meta"
        class="px-4 pb-4"
        data-testid="items-pagination"
        @page-change="$emit('page-change', $event)"
      />
    </div>

    <EmptyState v-else :title="$t('reports.no_data_title')" :description="$t('reports.no_data_desc')" icon="coffee" />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import Pagination from '../Common/Pagination.vue';
import ReportsNetValue from './ReportsNetValue.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useReturnsDisplay } from '../../Composables/useReturnsDisplay';

const { formatMoney, formatQty } = useFormatters();
const { moneyDetail, qtyDetail } = useReturnsDisplay();

defineProps({
  itemProfits: { type: Array, default: () => [] },
  totals: { type: Object, default: null },
  meta: { type: Object, default: () => ({}) },
  total: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

defineEmits(['page-change', 'retry']);
</script>
