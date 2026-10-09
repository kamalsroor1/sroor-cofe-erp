<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xl font-tajawal"
  >
    <DataTable
      data-testid="transfers-table"
      :rows="transfers"
      :columns="columns"
      :loading="isLoading || loading"
      :pagination="pagination"
      :empty-title="$t('inventory.no_transfers_found')"
      :empty-message="$t('inventory.no_movements_description')"
      @page-change="$emit('page-change', $event)"
    >
      <template #empty>
        <EmptyState
          :title="$t('inventory.no_transfers_found')"
          :description="$t('inventory.no_movements_description')"
          :icon="Truck"
        >
          <template #action>
            <BaseButton
              type="button"
              variant="gradient"
              size="md"
              :icon="Plus"
              :label="$t('inventory.create_first_transfer')"
              :to="'/stock-transfers/create'"
            />
          </template>
        </EmptyState>
      </template>

      <!-- Transfer Number -->
      <template #cell-transfer_number="{ row }">
        <button
          type="button"
          @click="$emit('preview', row)"
          class="font-mono font-bold text-theme-primary hover:underline cursor-pointer"
        >
          {{ row.transfer_number }}
        </button>
      </template>

      <!-- From Store -->
      <template #cell-from_store_name="{ row }">
        <span class="font-bold text-slate-700 dark:text-slate-300 font-tajawal">{{ row.from_store_name }}</span>
      </template>

      <!-- To Store -->
      <template #cell-to_store_name="{ row }">
        <span class="font-bold text-emerald-600 dark:text-emerald-400 font-tajawal">{{ row.to_store_name }}</span>
      </template>

      <!-- Date -->
      <template #cell-transfer_date="{ row }">
        <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">{{
          row.transfer_date
        }}</span>
      </template>

      <!-- Items Count -->
      <template #cell-items_count="{ row }">
        <span
          class="px-2 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-600 dark:text-cyan-400 text-xs font-mono font-bold"
        >
          {{ row.items_count }} {{ $t('inventory.item_unit') }}
        </span>
      </template>

      <!-- Status -->
      <template #cell-status="{ row }">
        <span
          class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block"
          :class="
            !row.is_cancelled
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500'
              : 'bg-rose-500/10 border-rose-500/30 text-rose-400'
          "
        >
          {{ !row.is_cancelled ? $t('inventory.transfer_status_done') : $t('inventory.transfer_status_cancelled') }}
        </span>
      </template>

      <!-- Actions -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center gap-1">
          <button
            type="button"
            @click="$emit('preview', row)"
            data-testid="action-view"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer flex items-center justify-center"
            :title="$t('inventory.view_transfer_details_hint')"
          >
            <Eye class="w-4 h-4" />
          </button>

          <ActionMenu
            :items="getTransferActions(row)"
            :title="$t('inventory.transfer_details_modal_title', { number: row.transfer_number })"
            button-class="h-8 w-8 min-w-[32px] p-0"
          />
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Eye, Ban, Plus, Truck } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import BaseButton from '@/Components/Common/BaseButton.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();

defineProps({
  transfers: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }),
  },
  isLoading: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['preview', 'cancel', 'page-change']);

const columns = computed(() => [
  { key: 'transfer_number', label: t('inventory.transfer_number'), mono: true },
  { key: 'from_store_name', label: t('inventory.from_store') },
  { key: 'to_store_name', label: t('inventory.to_store') },
  { key: 'transfer_date', label: t('common.date'), mono: true },
  { key: 'items_count', label: t('inventory.transfer_items'), align: 'center' },
  { key: 'status', label: t('common.status'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);

const getTransferActions = (trf) => [
  {
    label: t('inventory.view_transfer_details'),
    icon: Eye,
    onClick: () => emit('preview', trf),
  },
  {
    label: t('inventory.cancel_transfer'),
    icon: Ban,
    variant: 'danger',
    show: !trf.is_cancelled,
    onClick: () => emit('cancel', trf),
  },
];
</script>
