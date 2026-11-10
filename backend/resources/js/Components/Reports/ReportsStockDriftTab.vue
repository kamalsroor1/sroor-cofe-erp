<template>
  <div class="space-y-4 font-tajawal" data-testid="reports-stock-drift-tab">
    <div
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl"
    >
      <div
        class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3"
      >
        <div>
          <h3 class="text-sm font-black text-slate-900 dark:text-white">
            {{ $t('reports.stock_drift_title') }}
          </h3>
          <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-0.5">
            {{ $t('reports.stock_drift_subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <input
            v-model="search"
            type="text"
            :placeholder="$t('reports.stock_drift_search_placeholder')"
            data-testid="stock-drift-search"
            class="min-h-11 flex-1 sm:w-64 px-3 rounded-xl text-xs font-bold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-theme-primary"
            @input="onSearchInput"
          />
          <span
            class="min-h-11 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs text-slate-600 dark:text-slate-300 font-mono font-bold flex items-center"
          >
            {{ $t('reports.items_count_badge', { count: meta.total || 0 }) }}
          </span>
        </div>
      </div>

      <div v-if="isLoading" class="p-6">
        <TableSkeleton :rows="6" :cols="5" />
      </div>

      <div v-else-if="hasError" class="p-6">
        <ErrorState data-testid="stock-drift-error" :message="errorMessage" @retry="loadPage(meta.current_page)" />
      </div>

      <div v-else-if="items.length > 0">
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse" data-testid="stock-drift-table">
            <thead>
              <tr
                class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800"
              >
                <th class="p-3 text-start font-bold">{{ $t('inventory.item_name') }}</th>
                <th class="p-3 text-start font-bold">{{ $t('inventory.code') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('reports.stock_drift_col_item_stock') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('reports.stock_drift_col_branches_sum') }}</th>
                <th class="p-3 text-end font-bold">{{ $t('reports.stock_drift_col_drift') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
              <tr
                v-for="item in items"
                :key="item.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors"
                data-testid="stock-drift-row"
              >
                <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ item.name }}</td>
                <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ item.code || '—' }}</td>
                <td class="p-3 text-end font-mono font-bold text-slate-800 dark:text-slate-200" dir="ltr">
                  {{ item.current_stock }} {{ item.unit }}
                </td>
                <td class="p-3 text-end font-mono font-bold text-cyan-600 dark:text-cyan-400" dir="ltr">
                  {{ item.stores_stock_sum }} {{ item.unit }}
                </td>
                <td
                  class="p-3 text-end font-mono font-black"
                  :class="
                    String(item.drift).startsWith('-')
                      ? 'text-rose-600 dark:text-rose-400'
                      : 'text-amber-600 dark:text-amber-400'
                  "
                  dir="ltr"
                >
                  {{ formatSignedDrift(item.drift) }} {{ item.unit }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Tactile Cards -->
        <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
          <div
            v-for="item in items"
            :key="item.id"
            class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ item.name }}</h4>
                <span class="text-xs font-mono text-slate-400">{{ item.code || '—' }}</span>
              </div>
              <span
                class="text-sm font-black font-mono"
                :class="
                  String(item.drift).startsWith('-')
                    ? 'text-rose-600 dark:text-rose-400'
                    : 'text-amber-600 dark:text-amber-400'
                "
                dir="ltr"
              >
                {{ formatSignedDrift(item.drift) }} {{ item.unit }}
              </span>
            </div>
            <div
              class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs font-mono"
            >
              <span class="text-slate-500">
                {{ $t('reports.stock_drift_col_item_stock') }}: {{ item.current_stock }}
              </span>
              <span class="text-cyan-600 dark:text-cyan-400 font-bold">
                {{ $t('reports.stock_drift_col_branches_sum') }}: {{ item.stores_stock_sum }}
              </span>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div
          v-if="meta.last_page > 1"
          class="p-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2"
        >
          <button
            type="button"
            :disabled="meta.current_page <= 1"
            class="min-h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
            @click="loadPage(meta.current_page - 1)"
          >
            {{ $t('common.previous') }}
          </button>
          <span class="text-xs font-mono font-bold text-slate-500">
            {{ meta.current_page }} / {{ meta.last_page }}
          </span>
          <button
            type="button"
            :disabled="meta.current_page >= meta.last_page"
            class="min-h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 disabled:opacity-40 cursor-pointer"
            @click="loadPage(meta.current_page + 1)"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>

      <EmptyState
        v-else
        :title="$t('reports.stock_drift_empty_title')"
        :description="$t('reports.stock_drift_empty_desc')"
        icon="package"
      />
    </div>
  </div>
</template>

<script setup>
import { useStockDriftReport } from '../../Composables/useStockDriftReport';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';

const { items, isLoading, hasError, errorMessage, search, meta, loadPage, onSearchInput } = useStockDriftReport();

const formatSignedDrift = (val) => {
  const s = String(val ?? '0.000');
  return s.startsWith('-') || s === '0.000' ? s : `+${s}`;
};
</script>
