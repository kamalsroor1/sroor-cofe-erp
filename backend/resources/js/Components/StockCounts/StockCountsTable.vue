<script setup>
import { computed } from 'vue';
import { ClipboardList, Eye, Plus } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import StockCountStatusBadge from './StockCountStatusBadge.vue';
import { useTrans } from '@/Composables/useTrans';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
  counts: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  isLoading: { type: Boolean, default: false },
});

defineEmits(['open', 'start', 'page-change']);

const { t } = useTrans();
const { formatDateTime } = useFormatters();

const columns = computed(() => [
  { key: 'count_number', label: t('stock_counts.number'), mono: true },
  { key: 'store_name', label: t('stock_counts.store') },
  { key: 'category_name', label: t('stock_counts.category') },
  { key: 'progress', label: t('stock_counts.progress'), align: 'center' },
  { key: 'created_at', label: t('stock_counts.created_at'), mono: true },
  { key: 'status', label: t('stock_counts.status_label'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>

<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xl font-tajawal"
  >
    <DataTable
      data-testid="stock-counts-table"
      :rows="counts"
      :columns="columns"
      :loading="isLoading"
      :pagination="pagination"
      :empty-title="t('stock_counts.empty_title')"
      :empty-message="t('stock_counts.empty_description')"
      @page-change="$emit('page-change', $event)"
    >
      <template #empty>
        <EmptyState
          :title="t('stock_counts.empty_title')"
          :description="t('stock_counts.empty_description')"
          :icon="ClipboardList"
        >
          <template #action>
            <BaseButton
              variant="gradient"
              size="md"
              :icon="Plus"
              :label="t('stock_counts.new_count')"
              @click="$emit('start')"
            />
          </template>
        </EmptyState>
      </template>

      <template #cell-count_number="{ row }">
        <button
          type="button"
          class="font-mono font-bold text-theme-primary hover:underline cursor-pointer"
          @click="$emit('open', row)"
        >
          {{ row.count_number }}
        </button>
      </template>

      <template #cell-category_name="{ row }">
        <span class="text-slate-600 dark:text-slate-300">{{
          row.category_name || t('stock_counts.all_categories')
        }}</span>
      </template>

      <template #cell-progress="{ row }">
        <span class="font-mono text-xs text-slate-600 dark:text-slate-300">
          {{ t('stock_counts.counted_of', { counted: row.counted_lines_count ?? 0, total: row.lines_count ?? 0 }) }}
        </span>
      </template>

      <template #cell-created_at="{ row }">
        <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
          {{ formatDateTime(row.created_at) }}
        </span>
      </template>

      <template #cell-status="{ row }">
        <StockCountStatusBadge :status="row.status" />
      </template>

      <template #cell-actions="{ row }">
        <button
          type="button"
          data-testid="stock-count-open"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-1.5 text-slate-400 hover:text-theme-primary hover:bg-theme-light rounded-xl transition cursor-pointer inline-flex items-center justify-center"
          :title="row.is_open ? t('stock_counts.continue') : t('stock_counts.view')"
          :aria-label="row.is_open ? t('stock_counts.continue') : t('stock_counts.view')"
          @click="$emit('open', row)"
        >
          <Eye class="w-4 h-4" />
        </button>
      </template>
    </DataTable>
  </div>
</template>
