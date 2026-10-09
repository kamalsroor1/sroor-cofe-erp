<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl"
  >
    <ErrorState
      v-if="error"
      class="p-6"
      :message="typeof error === 'string' ? error : errorMessage"
      @retry="$emit('retry', pagination.current_page)"
    />
    <DataTable
      v-else
      data-testid="suppliers-table"
      :rows="suppliers"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      @page-change="$emit('page-change', $event)"
      :empty-title="$t('contacts.no_suppliers_found')"
      :empty-message="$t('contacts.no_suppliers_description')"
    >
      <template #empty-actions>
        <button
          type="button"
          @click="$emit('create')"
          class="px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary cursor-pointer"
        >
          {{ $t('contacts.add_first_supplier') }}
        </button>
      </template>

      <!-- # Cell -->
      <template #cell-index="{ rowIndex }">
        <div class="font-mono text-slate-500">
          {{ rowIndex + 1 + (pagination.current_page - 1) * pagination.per_page }}
        </div>
      </template>

      <!-- Supplier Cell -->
      <template #cell-supplier="{ row }">
        <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ row.name }}</div>
        <div
          v-if="row.address"
          class="text-[10px] text-slate-500 dark:text-slate-400 font-tajawal mt-0.5 max-w-xs truncate"
        >
          {{ row.address }}
        </div>
      </template>

      <!-- Company Name Cell -->
      <template #cell-company_name="{ row }">
        <div class="font-tajawal text-slate-700 dark:text-slate-300">
          {{ row.company_name || '—' }}
        </div>
      </template>

      <!-- Phone Cell -->
      <template #cell-phone="{ row }">
        <div class="font-mono text-slate-700 dark:text-slate-300" dir="ltr">
          {{ row.phone || '—' }}
        </div>
      </template>

      <!-- Balance Cell -->
      <template #cell-current_balance="{ row }">
        <div
          class="font-mono font-black text-sm text-end"
          :class="row.current_balance > 0 ? 'text-theme-primary' : 'text-emerald-500 dark:text-emerald-400'"
        >
          {{ formatMoney(row.current_balance) }}
          <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
        <div class="text-[10px] font-tajawal text-slate-500 dark:text-slate-400 mt-0.5 text-end">
          {{ row.current_balance > 0 ? $t('contacts.due_to_supplier') : $t('contacts.fully_settled') }}
        </div>
      </template>

      <!-- Status Cell -->
      <template #cell-status="{ row }">
        <div class="text-center">
          <span
            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal border"
            :class="
              row.is_active
                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
                : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-500'
            "
          >
            {{ row.is_active ? $t('common.active') : $t('common.inactive') }}
          </span>
        </div>
      </template>

      <!-- Actions Cell -->
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-center gap-1">
          <!-- Pay Supplier Button -->
          <button
            type="button"
            data-testid="action-pay"
            @click="$emit('pay', row)"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 px-2.5 py-1.5 bg-theme-light hover:bg-theme-hover/20 text-theme-primary border border-theme-border rounded-xl text-xs font-bold transition flex items-center gap-1 font-tajawal cursor-pointer active:scale-95"
            :title="$t('contacts.pay_supplier')"
          >
            <CreditCard class="w-3.5 h-3.5" />
            <span class="hidden lg:inline">{{ $t('contacts.pay_supplier') }}</span>
          </button>

          <!-- Statement Button -->
          <router-link
            :to="`/suppliers/${row.id}/statement`"
            data-testid="action-statement"
            class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition flex items-center justify-center active:scale-95"
            :title="$t('contacts.statement')"
          >
            <FileText class="w-4 h-4" />
          </router-link>

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
import { CreditCard, FileText, Pencil, Trash2 } from 'lucide-vue-next';
import DataTable from '../Common/DataTable.vue';
import ErrorState from '../Common/ErrorState.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { useTrans } from '../../Composables/useTrans';

const { formatMoney } = useFormatters();
const { t } = useTrans();

defineProps({
  suppliers: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  loading: { type: Boolean, default: false },
  error: { type: [Boolean, String, Object], default: null },
  errorMessage: { type: String, default: '' },
});

defineEmits(['create', 'pay', 'edit', 'delete', 'page-change', 'retry']);

const columns = computed(() => [
  { key: 'index', label: '#', align: 'start' },
  { key: 'supplier', label: t('purchases.supplier'), align: 'start' },
  { key: 'company_name', label: t('contacts.company_name'), align: 'start' },
  { key: 'phone', label: t('contacts.phone'), align: 'start' },
  { key: 'current_balance', label: t('contacts.payable_balance_label'), align: 'end' },
  { key: 'status', label: t('common.status'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>
