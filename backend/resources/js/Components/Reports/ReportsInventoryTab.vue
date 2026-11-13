<template>
  <div class="space-y-6 font-tajawal">
    <!-- Stock Valuation Top Cards -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4">
      <StatCardSkeleton v-for="i in 3" :key="i" />
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4">
      <div
        v-if="hasKey(inventoryData, 'stock_cost_valuation')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.stock_cost_val_label')
        }}</span>
        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
          {{ formatMoney(inventoryData.stock_cost_valuation) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
      </div>

      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.stock_sell_val_label')
        }}</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          {{ formatMoney(inventoryData.stock_selling_valuation) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
      </div>

      <div
        v-if="hasKey(inventoryData, 'expected_stock_profit')"
        class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-slate-900/90 border border-emerald-200 dark:border-emerald-500/30 shadow-sm dark:shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{
          $t('reports.expected_profit_val')
        }}</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          {{ formatMoney(inventoryData.expected_stock_profit) }}
          <span class="text-xs text-emerald-500 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
      </div>
    </div>

    <!-- Detailed Inventory Valuation Table -->
    <div
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl"
    >
      <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">
          {{ $t('reports.items_stock_valuation_title') }}
        </h3>
        <span v-if="!tableLoading" data-testid="inventory-count-badge" class="text-xs text-slate-500 font-mono">{{
          $t('reports.items_showing_badge', { shown: items.length, total })
        }}</span>
      </div>

      <div v-if="tableLoading" class="p-6">
        <TableSkeleton :rows="8" :cols="8" />
      </div>

      <ErrorState
        v-else-if="error && items.length === 0"
        data-testid="inventory-error"
        :message="errorMessage"
        @retry="$emit('retry')"
      />

      <div v-else-if="items.length > 0">
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr
                class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800"
              >
                <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('inventory.current_stock') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('inventory.average_cost') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('inventory.selling_price') }}</th>
                <th v-if="showCostVal" class="p-3 text-end font-bold">{{ $t('reports.cost_valuation') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('reports.selling_valuation') }}</th>
                <th v-if="showProfit" class="p-3 text-end font-bold">{{ $t('reports.expected_profit') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
              <tr
                v-for="itm in items"
                :key="itm.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors"
              >
                <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ itm.name }}</td>
                <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ itm.code || '—' }}</td>
                <td class="p-3 text-end font-mono font-bold text-cyan-600 dark:text-cyan-400">
                  {{ formatQty(itm.current_stock) }} {{ itm.unit }}
                </td>
                <td class="p-3 text-end font-mono text-slate-700 dark:text-slate-300">
                  <template v-if="effectiveAverageCost(itm) != null">
                    {{ formatMoney(effectiveAverageCost(itm)) }}
                  </template>
                </td>
                <td class="p-3 text-end font-mono text-emerald-500 dark:text-emerald-400">
                  {{ formatMoney(itm.selling_price) }}
                </td>
                <td v-if="showCostVal" class="p-3 text-end font-mono text-rose-500 dark:text-rose-400">
                  {{ formatMoney(itm.cost_val) }}
                </td>
                <td class="p-3 text-end font-mono font-bold text-slate-900 dark:text-white">
                  {{ formatMoney(itm.sell_val) }}
                </td>
                <td v-if="showProfit" class="p-3 text-end font-mono font-black text-emerald-500 dark:text-emerald-400">
                  {{ formatMoney(itm.profit) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Tactile Cards -->
        <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
          <div
            v-for="itm in items"
            :key="itm.id"
            class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ itm.name }}</h4>
                <span class="text-xs font-mono text-slate-400">{{ itm.code || '—' }}</span>
              </div>
              <div class="text-end">
                <span class="text-xs font-mono font-bold text-cyan-500"
                  >{{ formatQty(itm.current_stock) }} {{ itm.unit }}</span
                >
                <span v-if="hasKey(itm, 'profit')" class="text-sm font-black font-mono text-emerald-500 block"
                  >+{{ formatMoney(itm.profit) }}</span
                >
              </div>
            </div>
            <div
              class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs font-mono"
            >
              <span v-if="hasKey(itm, 'cost_val')" class="text-slate-500"
                >{{ $t('reports.cost_valuation') }}: {{ formatMoney(itm.cost_val) }}</span
              >
              <span class="text-slate-900 dark:text-white font-bold"
                >{{ $t('reports.selling_valuation') }}: {{ formatMoney(itm.sell_val) }}</span
              >
            </div>
          </div>
        </div>

        <Pagination
          :pagination="meta"
          class="px-4 pb-4"
          data-testid="inventory-pagination"
          @page-change="$emit('page-change', $event)"
        />
      </div>

      <EmptyState
        v-else
        :title="$t('reports.no_data_title')"
        :description="$t('reports.no_data_desc')"
        icon="package"
      />
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import Pagination from '../Common/Pagination.vue';
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import { effectiveAverageCost } from '../../helpers/itemCost';

const { formatMoney, formatQty, currencyLabel } = useFormatters();

const props = defineProps({
  inventoryData: { type: Object, default: () => ({}) },
  items: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({}) },
  total: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  tableLoading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

defineEmits(['page-change', 'retry']);

// The plan may strip cost/profit keys from the payload: a missing key hides the column or card.
const hasKey = (obj, key) => obj != null && Object.prototype.hasOwnProperty.call(obj, key);
const showCostVal = computed(() => props.items.some((itm) => hasKey(itm, 'cost_val')));
const showProfit = computed(() => props.items.some((itm) => hasKey(itm, 'profit')));
</script>
