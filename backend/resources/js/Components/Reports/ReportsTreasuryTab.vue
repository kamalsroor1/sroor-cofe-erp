<template>
  <div class="space-y-6 font-tajawal">
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCardSkeleton v-for="i in 3" :key="i" />
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total Inflow -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t('reports.total_inflow_label') }}</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
          +{{ formatMoney(treasuryData.total_inflows || 0) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
      </div>

      <!-- Total Outflow -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.total_outflow_label')
        }}</span>
        <div class="text-2xl font-black text-rose-500 dark:text-rose-400 font-mono">
          -{{ formatMoney(treasuryData.total_outflows || 0) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
        <span
          v-if="Number(treasuryData.total_refunds || 0) > 0"
          class="text-[11px] text-slate-500 dark:text-slate-400 block"
          data-testid="treasury-total-refunds"
        >
          {{ $t('treasury.cash_refunds_line') }}: {{ formatMoney(treasuryData.total_refunds) }} {{ currencyLabel }}
        </span>
      </div>

      <!-- Net Cash Flow -->
      <div
        class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
      >
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{
          $t('reports.net_cash_flow_label')
        }}</span>
        <div
          class="text-2xl font-black font-mono"
          :class="
            Number(treasuryData.total_net_change || 0) >= 0 ? 'text-cyan-500 dark:text-cyan-400' : 'text-rose-500'
          "
        >
          {{ Number(treasuryData.total_net_change || 0) > 0 ? '+' : ''
          }}{{ formatMoney(treasuryData.total_net_change || 0) }}
          <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-4 space-y-3"
    >
      <div class="flex items-center justify-between gap-2">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.treasury_ledger_title') }}</h3>
        <span v-if="!ledgerLoading" data-testid="treasury-ledger-badge" class="text-xs text-slate-500 font-mono">{{
          $t('reports.ledger_showing_badge', { shown: ledger.length, total })
        }}</span>
      </div>

      <DataTable
        data-testid="treasury-ledger-table"
        :rows="ledger"
        :columns="columns"
        :loading="ledgerLoading"
        :skeleton-rows="8"
        :pagination="meta"
        :error="error"
        :error-message="errorMessage"
        :empty-title="$t('reports.no_data_title')"
        :empty-message="$t('reports.no_data_desc')"
        @page-change="$emit('page-change', $event)"
        @retry="$emit('retry')"
      >
        <template #cell-date="{ row }">
          <div class="font-mono text-slate-700 dark:text-slate-300">{{ row.date }} {{ row.time }}</div>
        </template>
        <template #cell-doc_number="{ row }">
          <div class="font-mono text-theme-primary">{{ row.doc_number || '—' }}</div>
        </template>
        <template #cell-debit="{ row }">
          <div class="font-mono font-bold text-end text-emerald-600 dark:text-emerald-400">
            {{ Number(row.debit) !== 0 ? formatMoney(row.debit) : '—' }}
          </div>
        </template>
        <template #cell-credit="{ row }">
          <div class="font-mono font-bold text-end text-rose-500 dark:text-rose-400">
            {{ Number(row.credit) !== 0 ? formatMoney(row.credit) : '—' }}
          </div>
        </template>
        <template #cell-running_balance="{ row }">
          <div class="font-mono font-black text-end text-slate-900 dark:text-white">
            {{ formatMoney(row.running_balance) }}
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import DataTable from '../Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney, currencyLabel } = useFormatters();
const { t } = useTrans();

defineProps({
  treasuryData: { type: Object, default: () => ({}) },
  ledger: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({}) },
  total: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  ledgerLoading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
});

defineEmits(['page-change', 'retry']);

const columns = computed(() => [
  { key: 'date', label: t('common.date'), align: 'start' },
  { key: 'doc_number', label: t('reports.treasury_col_doc'), align: 'start' },
  { key: 'type_label', label: t('reports.treasury_col_type'), align: 'start' },
  { key: 'method_label', label: t('reports.treasury_col_method'), align: 'start' },
  { key: 'party', label: t('reports.treasury_col_party'), align: 'start' },
  { key: 'debit', label: t('reports.treasury_col_in'), align: 'end' },
  { key: 'credit', label: t('reports.treasury_col_out'), align: 'end' },
  { key: 'running_balance', label: t('reports.treasury_col_balance'), align: 'end' },
]);
</script>
