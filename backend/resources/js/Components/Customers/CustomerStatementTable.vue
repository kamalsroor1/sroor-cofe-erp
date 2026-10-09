<template>
  <DataTable
    data-testid="customer-statement-table"
    :rows="ledger"
    :columns="columns"
    :loading="loading"
    :empty-title="$t('contacts.no_transactions_found')"
    :empty-message="$t('contacts.no_transactions_in_period')"
  >
    <!-- # Cell -->
    <template #cell-index="{ rowIndex }">
      <div class="font-mono text-slate-500">{{ rowIndex + 1 }}</div>
    </template>

    <!-- Date Cell -->
    <template #cell-date="{ row }">
      <div class="font-mono text-slate-700 dark:text-slate-300">{{ row.date }}</div>
    </template>

    <!-- Type Cell -->
    <template #cell-type="{ row }">
      <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.type }}</div>
    </template>

    <!-- Reference No Cell -->
    <template #cell-ref_number="{ row }">
      <div class="font-mono text-theme-primary">{{ row.ref_number || '—' }}</div>
    </template>

    <!-- Debit Cell -->
    <template #cell-debit="{ row }">
      <div
        class="font-mono font-bold text-end"
        :class="row.debit > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-400'"
      >
        {{ formatMoney(row.debit) }}
      </div>
    </template>

    <!-- Credit Cell -->
    <template #cell-credit="{ row }">
      <div
        class="font-mono font-bold text-end"
        :class="row.credit > 0 ? 'text-emerald-500 dark:text-emerald-400' : 'text-slate-400'"
      >
        {{ formatMoney(row.credit) }}
      </div>
    </template>

    <!-- Closing Balance Cell -->
    <template #cell-balance_after="{ row }">
      <div
        class="font-mono font-black text-end"
        :class="
          row.balance_after > 0
            ? 'text-rose-500 dark:text-rose-400'
            : row.balance_after < 0
              ? 'text-cyan-500 dark:text-cyan-400'
              : 'text-emerald-500 dark:text-emerald-400'
        "
      >
        {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
      </div>
    </template>

    <!-- Notes Cell -->
    <template #cell-notes="{ row }">
      <div class="font-tajawal text-slate-500 dark:text-slate-400 max-w-xs truncate">
        {{ row.notes || '—' }}
      </div>
    </template>
  </DataTable>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from '../Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  ledger: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const columns = computed(() => [
  { key: 'index', label: '#', align: 'start' },
  { key: 'date', label: t('common.date'), align: 'start' },
  { key: 'type', label: t('contacts.transaction_type'), align: 'start' },
  { key: 'ref_number', label: t('contacts.reference_no'), align: 'start' },
  { key: 'debit', label: `${t('contacts.period_debit')} (${t('contacts.withdrawals')})`, align: 'end' },
  { key: 'credit', label: `${t('contacts.period_credit')} (${t('contacts.payments_received')})`, align: 'end' },
  { key: 'balance_after', label: t('contacts.closing_balance'), align: 'end' },
  { key: 'notes', label: t('common.notes'), align: 'start' },
]);
</script>
