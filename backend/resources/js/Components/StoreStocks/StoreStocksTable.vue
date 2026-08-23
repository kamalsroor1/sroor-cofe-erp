<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="stocks.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_name') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('inventory.item_code') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.unit') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.current_stock') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('inventory.min_stock_level') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.cost_price') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('inventory.total_valuation') }}</th>
              <th class="py-3 px-4 text-center font-bold">{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(stock, idx) in stocks"
              :key="stock.id"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
              :class="stock.is_out_of_stock ? 'bg-rose-500/5 dark:bg-rose-500/10' : (stock.is_low_stock ? 'bg-amber-500/5 dark:bg-amber-500/10' : '')"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 + (pagination.current_page - 1) * pagination.per_page }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ stock.item_name }}</td>
              <td class="py-3.5 px-4 font-mono text-slate-400">{{ stock.item_code || '—' }}</td>
              <td class="py-3.5 px-4 text-center text-slate-600 dark:text-slate-300">{{ stock.unit || $t('inventory.unit_piece_short') }}</td>
              <td class="py-3.5 px-4 text-center font-mono font-black text-sm" :class="stock.is_out_of_stock ? 'text-rose-500' : (stock.is_low_stock ? 'text-amber-500' : 'text-emerald-500')">
                {{ formatQty(stock.quantity) }}
              </td>
              <td class="py-3.5 px-4 text-center font-mono text-slate-400">{{ formatQty(stock.min_stock_level) }}</td>
              <td class="py-3.5 px-4 text-end font-mono text-slate-700 dark:text-slate-300">{{ formatMoney(stock.cost_price) }} {{ $t('common.currency') }}</td>
              <td class="py-3.5 px-4 text-end font-mono font-bold text-theme-primary">{{ formatMoney(stock.total_valuation) }} {{ $t('common.currency') }}</td>
              <td class="py-3.5 px-4 text-center">
                <span
                  v-if="stock.is_out_of_stock"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30"
                >
                  {{ $t('inventory.out_of_stock_badge') }}
                </span>
                <span
                  v-else-if="stock.is_low_stock"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30"
                >
                  {{ $t('inventory.low_stock_badge') }}
                </span>
                <span
                  v-else
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30"
                >
                  {{ $t('inventory.available_badge') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="stock in stocks"
          :key="stock.id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60"
          :class="stock.is_out_of_stock ? 'bg-rose-500/5 border-rose-300 dark:border-rose-500/30' : (stock.is_low_stock ? 'bg-amber-500/5 border-amber-300 dark:border-amber-500/30' : '')"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ stock.item_name }}</h4>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{{ stock.item_code || '—' }} • {{ stock.unit || $t('inventory.unit_piece_short') }}</p>
            </div>
            <span
              v-if="stock.is_out_of_stock"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30 shrink-0"
            >
              {{ $t('inventory.out_of_stock_badge') }}
            </span>
            <span
              v-else-if="stock.is_low_stock"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 shrink-0"
            >
              {{ $t('inventory.low_stock_badge') }}
            </span>
            <span
              v-else
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shrink-0"
            >
              {{ $t('inventory.available_badge') }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs font-mono">
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.current_stock') }}:</span>
              <span class="font-black" :class="stock.is_out_of_stock ? 'text-rose-500' : (stock.is_low_stock ? 'text-amber-500' : 'text-emerald-500')">
                {{ formatQty(stock.quantity) }} {{ stock.unit }}
              </span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.min_stock_level') }}:</span>
              <span class="font-bold text-slate-700 dark:text-slate-300">{{ formatQty(stock.min_stock_level) }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.cost_price') }}:</span>
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatMoney(stock.cost_price) }} {{ $t('common.currency') }}</span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 font-sans block">{{ $t('inventory.total_valuation') }}:</span>
              <span class="font-black text-theme-primary">{{ formatMoney(stock.total_valuation) }} {{ $t('common.currency') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('inventory.no_stocks_found')"
      :description="$t('inventory.no_stocks_match_filter')"
      icon="📦"
    />

    <!-- Pagination Bar -->
    <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="text-xs text-slate-400">
        {{ $t('inventory.total_results_items', { count: pagination.total }) }}
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="$emit('page-change', pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="min-h-[38px] px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
        >
          {{ $t('common.previous') }}
        </button>
        <span class="px-3 py-1.5 text-xs font-mono text-slate-700 dark:text-slate-300 font-bold">
          {{ pagination.current_page }} / {{ pagination.last_page }}
        </span>
        <button
          type="button"
          @click="$emit('page-change', pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="min-h-[38px] px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
        >
          {{ $t('common.next') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, formatQty } = useFormatters();

defineProps({
  stocks: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['page-change']);
</script>
