<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal"
  >
    <DataTable
      data-testid="expenses-table"
      :rows="expenses"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :error="error"
      :error-message="errorMessage"
      @retry="$emit('retry', $event)"
      @page-change="$emit('page-change', $event)"
      :empty-title="$t('expenses.no_expenses_found')"
      :empty-message="$t('expenses.no_expenses_description')"
    >
      <template #empty-actions>
        <button
          type="button"
          @click="$emit('create')"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary cursor-pointer"
        >
          {{ $t('expenses.add_first_expense') }}
        </button>
      </template>

      <!-- # Cell -->
      <template #cell-index="{ rowIndex }">
        <div class="font-mono text-slate-500">
          {{ rowIndex + 1 + (pagination.current_page - 1) * pagination.per_page }}
        </div>
      </template>

      <!-- Invoice Number Cell -->
      <template #cell-expense_number="{ row }">
        <div class="font-mono font-bold text-theme-primary">
          {{ row.expense_number }}
        </div>
      </template>

      <!-- Item Cell -->
      <template #cell-item="{ row }">
        <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ row.title }}</div>
        <div
          v-if="row.notes"
          class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5 max-w-xs truncate"
        >
          {{ row.notes }}
        </div>
      </template>

      <!-- Cost Center & Category Cell -->
      <template #cell-cost_center="{ row }">
        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 font-tajawal">
          {{ row.cost_center_label || row.cost_center }}
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5">
          {{ row.category }}
        </div>
      </template>

      <!-- Date Cell -->
      <template #cell-date="{ row }">
        <div class="font-mono text-slate-700 dark:text-slate-300">
          {{ row.expense_date }}
        </div>
      </template>

      <!-- Amount Cell -->
      <template #cell-amount="{ row }">
        <div class="text-end font-mono font-black text-sm text-rose-500 dark:text-rose-400">
          {{ formatMoney(row.amount) }}
          <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
      </template>

      <!-- Payment Method Cell -->
      <template #cell-payment_method="{ row }">
        <div class="text-center">
          <span
            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300"
          >
            {{ formatPaymentMethod(row.payment_method) }}
          </span>
        </div>
      </template>

      <!-- Actions Cell -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center gap-1">
          <!-- Edit Button -->
          <button
            type="button"
            data-testid="action-edit"
            @click="$emit('edit', row)"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition flex items-center justify-center cursor-pointer active:scale-95"
            :title="$t('common.edit')"
          >
            <Pencil class="w-4 h-4" />
          </button>

          <!-- Delete Button -->
          <button
            type="button"
            data-testid="action-delete"
            @click="$emit('delete', row)"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition flex items-center justify-center cursor-pointer active:scale-95"
            :title="$t('common.delete')"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Pencil, Trash2 } from 'lucide-vue-next';
import DataTable from '../Common/DataTable.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  expenses: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  loading: { type: Boolean, default: false },
  error: { type: [Boolean, String, Object], default: null },
  errorMessage: { type: String, default: '' },
});

defineEmits(['create', 'edit', 'delete', 'page-change', 'retry']);

const formatPaymentMethod = (method) => {
  const map = {
    cash: t('contacts.cash'),
    instapay: t('contacts.instapay'),
    e_wallet: t('contacts.wallet'),
    visa: t('treasury.method_visa'),
    bank_transfer: t('contacts.bank_transfer'),
    check: t('invoices.check'),
  };
  return map[method] || method;
};

const columns = computed(() => [
  { key: 'index', label: '#', align: 'start' },
  { key: 'expense_number', label: t('invoices.invoice_number'), align: 'start' },
  { key: 'item', label: t('expenses.expense_item'), align: 'start' },
  { key: 'cost_center', label: t('expenses.cost_center_and_category'), align: 'start' },
  { key: 'date', label: t('common.date'), align: 'start' },
  { key: 'amount', label: t('common.amount'), align: 'end' },
  { key: 'payment_method', label: t('invoices.payment_method'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>
