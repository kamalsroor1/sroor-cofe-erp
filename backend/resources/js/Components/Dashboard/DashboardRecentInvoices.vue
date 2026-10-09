<template>
  <DashboardSectionCard :title="$t('dashboard.recent_invoices')" dot-color="bg-emerald-500">
    <template #action>
      <router-link
        to="/invoices"
        class="text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-theme-primary transition flex items-center gap-1 cursor-pointer"
      >
        <span>{{ $t('dashboard.all_invoices') }}</span>
        <span>←</span>
      </router-link>
    </template>
    <DataTable
      data-testid="dashboard-recent-invoices-table"
      :rows="invoices"
      :columns="columns"
      :empty-title="$t('dashboard.no_invoices')"
    >
      <template #cell-invoice_number="{ row }">
        <span class="text-cyan-600 dark:text-cyan-400 font-bold font-mono">{{ row.invoice_number }}</span>
      </template>

      <template #cell-customer_name="{ row }">
        <span class="font-sans font-bold text-slate-800 dark:text-slate-200">{{ row.customer_name }}</span>
      </template>

      <template #cell-invoice_date="{ row }">
        <span class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
          {{ row.invoice_date || row.created_at }}
        </span>
      </template>

      <template #cell-net_total="{ row }">
        <span class="font-bold text-slate-900 dark:text-white font-mono">
          {{ formatMoney(row.net_total) }}
          <span class="text-[10px] font-sans text-slate-400">{{ $t('common.currency') }}</span>
        </span>
      </template>

      <template #cell-status="{ row }">
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border font-sans" :class="statusBadge(row)">
          {{ statusLabel(row) }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex justify-end font-sans">
          <button
            type="button"
            @click="$emit('preview', row)"
            data-testid="action-preview"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-[11px] font-bold transition cursor-pointer border border-slate-300 dark:border-slate-700 active:scale-95 flex items-center justify-center"
          >
            {{ $t('dashboard.preview_print') }}
          </button>
        </div>
      </template>
    </DataTable>
  </DashboardSectionCard>
</template>

<script setup>
import { computed } from 'vue';
import DashboardSectionCard from '../Common/DashboardSectionCard.vue';
import DataTable from '@/Components/Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({ invoices: { type: Array, default: () => [] } });
defineEmits(['preview']);

const columns = computed(() => [
  { key: 'invoice_number', label: t('dashboard.invoice_number') },
  { key: 'customer_name', label: t('dashboard.customer') },
  { key: 'invoice_date', label: t('dashboard.date') },
  { key: 'net_total', label: t('dashboard.total') },
  { key: 'status', label: t('dashboard.status') },
  { key: 'actions', label: t('dashboard.actions') },
]);

const statusBadge = (inv) => {
  if (inv.status === 'cancelled') return 'bg-rose-500/10 text-rose-500 border-rose-500/30';
  if (inv.remaining_amount > 0) return 'bg-theme-light text-theme-primary border-theme-border';
  return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30';
};
const statusLabel = (inv) => {
  if (inv.status === 'cancelled') return t('dashboard.status_cancelled');
  if (inv.remaining_amount > 0) return t('dashboard.status_credit');
  return t('dashboard.status_paid');
};
</script>
