<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl"
  >
    <DataTable
      data-testid="item-movements-table"
      :rows="movements"
      :columns="columns"
      :loading="loading"
      :empty-title="$t('inventory.no_movements_found')"
      :empty-message="$t('inventory.no_movements_description')"
    >
      <template #cell-index="{ index }">
        <span class="font-mono text-slate-500">{{ index + 1 }}</span>
      </template>

      <template #cell-created_at="{ row }">
        <span class="font-mono text-slate-700 dark:text-slate-300">{{ row.created_at }}</span>
      </template>

      <template #cell-movement_type="{ row }">
        <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold border" :class="getMovementBadge(row.movement_type)">
          {{ formatMovementLabel(row.movement_type) }}
        </span>
      </template>

      <template #cell-document_number="{ row }">
        <span class="font-mono text-theme-primary font-bold">{{ row.document_number || '—' }}</span>
      </template>

      <template #cell-quantity="{ row }">
        <span
          class="font-mono font-black"
          :class="isPositiveMovement(row.movement_type) ? 'text-emerald-500 dark:text-emerald-400' : 'text-rose-500'"
        >
          {{ isPositiveMovement(row.movement_type) ? '+' : '-' }}{{ formatQty(row.quantity) }}
        </span>
      </template>

      <template #cell-stock_before="{ row }">
        <span class="font-mono text-slate-500 dark:text-slate-400">
          {{ formatQty(row.stock_before) }}
        </span>
      </template>

      <template #cell-stock_after="{ row }">
        <span class="font-mono font-black text-slate-900 dark:text-white">
          {{ formatQty(row.stock_after) }}
        </span>
      </template>

      <template #cell-store_user="{ row }">
        <div class="font-tajawal text-slate-700 dark:text-slate-300">
          <div class="font-bold">{{ row.store?.name || $t('common.main_branch') }}</div>
          <div class="text-[10px] text-slate-400">{{ row.user?.name || $t('common.system') }}</div>
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatQty } = useFormatters();
const { t } = useTrans();

defineProps({
  movements: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  getMovementBadge: { type: Function, required: true },
  formatMovementLabel: { type: Function, required: true },
  isPositiveMovement: { type: Function, required: true },
});

const columns = computed(() => [
  { key: 'index', label: '#' },
  { key: 'created_at', label: t('common.date') },
  { key: 'movement_type', label: t('inventory.movement_type') },
  { key: 'document_number', label: t('contacts.reference_no') },
  { key: 'quantity', label: t('common.quantity') },
  { key: 'stock_before', label: t('inventory.stock_before') },
  { key: 'stock_after', label: t('inventory.stock_after') },
  { key: 'store_user', label: t('inventory.store_user') },
]);
</script>
