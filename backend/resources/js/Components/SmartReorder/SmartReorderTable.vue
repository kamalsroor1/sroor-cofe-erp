<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl"
  >
    <DataTable
      data-testid="smart-reorder-table"
      :rows="suggestions"
      :columns="columns"
      :loading="loading"
      :empty-title="$t('purchases.no_shortages_found_title')"
      :empty-message="$t('purchases.no_shortages_found_desc')"
      :row-class="(row) => (row.urgency === 'critical' ? 'bg-rose-500/5 dark:bg-rose-500/10' : '')"
    >
      <template #header-checkbox>
        <div class="flex items-center justify-center w-full">
          <input
            type="checkbox"
            @change="$emit('toggle-select-all')"
            :checked="isAllSelected"
            class="rounded border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-0 cursor-pointer w-4 h-4"
          />
        </div>
      </template>

      <template #cell-checkbox="{ row }">
        <div class="flex items-center justify-center w-full">
          <input
            type="checkbox"
            :checked="selectedIds.includes(row.id)"
            @change="$emit('toggle-item', row)"
            class="rounded border-slate-300 dark:border-slate-700 text-theme-primary focus:ring-0 cursor-pointer w-4 h-4"
          />
        </div>
      </template>

      <template #cell-name="{ row }">
        <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ row.name }}</div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
          {{ row.code || '—' }} ({{ row.unit }})
        </div>
      </template>

      <template #cell-current_stock="{ row }">
        <span
          class="font-mono font-black text-sm"
          :class="row.current_stock <= 0 ? 'text-rose-500' : 'text-slate-900 dark:text-slate-200'"
        >
          {{ row.current_stock }}
        </span>
      </template>

      <template #cell-avg_daily_consumption="{ row }">
        <span class="font-mono text-slate-500 dark:text-slate-400">
          {{ row.avg_daily_consumption || '0.00' }} {{ $t('purchases.per_day') }}
        </span>
      </template>

      <template #cell-days_remaining="{ row }">
        <span class="font-mono font-bold" :class="row.days_remaining <= 3 ? 'text-rose-500' : 'text-theme-primary'">
          {{
            row.days_remaining !== null
              ? $t('purchases.days_count', { count: row.days_remaining })
              : $t('purchases.not_specified')
          }}
        </span>
      </template>

      <template #cell-suggested_reorder_qty="{ row }">
        <span class="font-mono font-black text-theme-primary text-sm">
          {{ row.suggested_reorder_qty }} {{ row.unit }}
        </span>
      </template>

      <template #cell-estimated_cost="{ row }">
        <span class="font-mono font-bold text-emerald-500 dark:text-emerald-400">
          {{ formatMoney(row.estimated_cost || 0) }} {{ $t('common.currency') }}
        </span>
      </template>

      <template #cell-urgency="{ row }">
        <span
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border font-tajawal"
          :class="getUrgencyBadge(row.urgency)"
        >
          {{ getUrgencyText(row.urgency) }}
        </span>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  suggestions: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  isAllSelected: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['toggle-select-all', 'toggle-item']);

const columns = computed(() => [
  { key: 'checkbox', label: '' },
  { key: 'name', label: t('purchases.item_and_code') },
  { key: 'current_stock', label: t('inventory.current_stock') },
  { key: 'avg_daily_consumption', label: t('purchases.daily_usage') },
  { key: 'days_remaining', label: t('purchases.stock_lasts_for') },
  { key: 'suggested_reorder_qty', label: t('purchases.suggested_qty') },
  { key: 'estimated_cost', label: t('purchases.estimated_cost') },
  { key: 'urgency', label: t('purchases.risk_level') },
]);

const getUrgencyBadge = (urgency) => {
  switch (urgency) {
    case 'critical':
      return 'bg-rose-500/10 border-rose-500/30 text-rose-500 dark:text-rose-400';
    case 'warning':
      return 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400';
    default:
      return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400';
  }
};

const getUrgencyText = (urgency) => {
  switch (urgency) {
    case 'critical':
      return t('purchases.urgency_critical_badge');
    case 'warning':
      return t('purchases.urgency_warning_badge');
    default:
      return t('purchases.urgency_safe_badge');
  }
};
</script>
