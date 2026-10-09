<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal"
  >
    <DataTable
      data-testid="store-stocks-table"
      :rows="stocks"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :empty-title="$t('inventory.no_stocks_found')"
      :empty-message="$t('inventory.no_stocks_match_filter')"
      @page-change="$emit('page-change', $event)"
    >
      <template #empty>
        <EmptyState
          :title="$t('inventory.no_stocks_found')"
          :description="$t('inventory.no_stocks_match_filter')"
          :icon="Package"
        />
      </template>

      <!-- Item Name -->
      <template #cell-item_name="{ row }">
        <span class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.item_name }}</span>
      </template>

      <!-- Item Code -->
      <template #cell-item_code="{ row }">
        <span class="font-mono text-slate-400">{{ row.item_code || '—' }}</span>
      </template>

      <!-- Unit -->
      <template #cell-unit="{ row }">
        <span class="text-slate-600 dark:text-slate-300">
          {{ row.unit || $t('inventory.unit_piece_short') }}
        </span>
      </template>

      <!-- Current Stock -->
      <template #cell-quantity="{ row }">
        <span
          class="font-mono font-black text-sm"
          :class="row.is_out_of_stock ? 'text-rose-500' : row.is_low_stock ? 'text-amber-500' : 'text-emerald-500'"
        >
          {{ formatQty(row.quantity) }}
        </span>
      </template>

      <!-- Min Stock Level -->
      <template #cell-min_stock_level="{ row }">
        <span class="font-mono text-slate-400">{{ formatQty(row.min_stock_level) }}</span>
      </template>

      <!-- Cost Price -->
      <template #cell-cost_price="{ row }">
        <span class="font-mono text-slate-700 dark:text-slate-300">
          {{ formatMoney(row.cost_price) }} {{ $t('common.currency') }}
        </span>
      </template>

      <!-- Total Valuation -->
      <template #cell-total_valuation="{ row }">
        <span class="font-mono font-bold text-theme-primary">
          {{ formatMoney(row.total_valuation) }} {{ $t('common.currency') }}
        </span>
      </template>

      <!-- Status -->
      <template #cell-status="{ row }">
        <span
          v-if="row.is_out_of_stock"
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30 inline-block"
        >
          {{ $t('inventory.out_of_stock_badge') }}
        </span>
        <span
          v-else-if="row.is_low_stock"
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 inline-block"
        >
          {{ $t('inventory.low_stock_badge') }}
        </span>
        <span
          v-else
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 inline-block"
        >
          {{ $t('inventory.available_badge') }}
        </span>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const { formatMoney, formatQty } = useFormatters();

defineProps({
  stocks: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['page-change']);

const columns = computed(() => [
  { key: 'item_name', label: t('inventory.item_name') },
  { key: 'item_code', label: t('inventory.item_code'), mono: true },
  { key: 'unit', label: t('inventory.unit'), align: 'center' },
  { key: 'quantity', label: t('inventory.current_stock'), align: 'center', mono: true },
  { key: 'min_stock_level', label: t('inventory.min_stock_level'), align: 'center', mono: true },
  { key: 'cost_price', label: t('inventory.cost_price'), align: 'end', mono: true },
  { key: 'total_valuation', label: t('inventory.total_valuation'), align: 'end', mono: true },
  { key: 'status', label: t('common.status'), align: 'center' },
]);
</script>
