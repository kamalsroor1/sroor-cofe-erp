<script setup>
import { computed } from 'vue';
import { Receipt, FileText, Pencil, Trash2, Users } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useTrans } from '@/Composables/useTrans';

defineProps({
  customers: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 }) },
  loading: { type: Boolean, default: false },
});

defineEmits(['create', 'pay', 'edit', 'delete', 'page-change']);

const { formatMoney } = useFormatters();
const { t } = useTrans();

const columns = computed(() => [
  { key: 'index', label: '#', width: 'w-16', mono: true, hideOnMobile: true },
  { key: 'name', label: t('contacts.customer_name') },
  { key: 'phone', label: t('contacts.phone'), mono: true },
  { key: 'address', label: t('contacts.address') },
  { key: 'balance', label: t('contacts.current_balance'), align: 'end' },
  { key: 'status', label: t('common.status'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>

<template>
  <DataTable
    :columns="columns"
    :rows="customers"
    :loading="loading"
    :pagination="pagination"
    :empty-title="$t('contacts.no_customers_found')"
    :empty-message="$t('contacts.no_customers_description')"
    :empty-icon="Users"
    data-testid="customers-table"
    @page-change="$emit('page-change', $event)"
  >
    <!-- Custom Empty State Action -->
    <template #empty>
      <div class="flex flex-col items-center justify-center p-8 text-center space-y-4">
        <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400">
          <Users class="w-8 h-8" />
        </div>
        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-200">{{ $t('contacts.no_customers_found') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">{{ $t('contacts.no_customers_description') }}</p>
        <button
          type="button"
          @click="$emit('create')"
          class="mt-2 px-5 py-2.5 bg-theme-primary text-white font-bold rounded-xl text-xs font-black shadow-lg shadow-theme-primary cursor-pointer active:scale-95"
        >
          {{ $t('contacts.add_first_customer') }}
        </button>
      </div>
    </template>

    <!-- Cells -->
    <template #cell-index="{ index }">
      <span class="text-slate-500">
        {{ index + 1 + ((pagination?.current_page || 1) - 1) * (pagination?.per_page || 15) }}
      </span>
    </template>

    <template #cell-name="{ row }">
      <div class="font-bold text-slate-900 dark:text-white font-tajawal text-sm">{{ row.name }}</div>
      <div v-if="row.tax_number" class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
        {{ $t('contacts.tax_number_label') }} {{ row.tax_number }}
      </div>
    </template>

    <template #cell-phone="{ row }">
      <span class="text-slate-700 dark:text-slate-300" dir="ltr">
        {{ row.phone || '—' }}
      </span>
    </template>

    <template #cell-address="{ row }">
      <span class="font-tajawal text-slate-700 dark:text-slate-400 max-w-xs truncate block">
        {{ row.address || '—' }}
      </span>
    </template>

    <template #cell-balance="{ row }">
      <div class="flex flex-col" :class="{ 'items-end': true, 'items-start lg:items-end': false }">
        <div
          class="font-mono font-black text-sm"
          :class="
            row.current_balance > 0
              ? 'text-rose-500 dark:text-rose-400'
              : row.current_balance < 0
                ? 'text-cyan-500 dark:text-cyan-400'
                : 'text-emerald-500 dark:text-emerald-400'
          "
        >
          {{ formatMoney(row.current_balance) }}
          <span class="text-xs font-normal font-tajawal">{{ $t('common.currency') }}</span>
        </div>
        <div class="text-[10px] font-tajawal text-slate-500 dark:text-slate-400 mt-0.5">
          {{
            row.current_balance > 0
              ? $t('contacts.debt_due')
              : row.current_balance < 0
                ? $t('contacts.credit_balance')
                : $t('contacts.settled')
          }}
        </div>
      </div>
    </template>

    <template #cell-status="{ row }">
      <span
        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-tajawal border inline-block"
        :class="
          row.is_active
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'
            : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-500'
        "
      >
        {{ row.is_active ? $t('common.active') : $t('common.inactive') }}
      </span>
    </template>

    <!-- Actions slot handles both desktop and mobile -->
    <template #actions="{ row }">
      <div class="flex items-center justify-center gap-1 flex-wrap">
        <button
          type="button"
          @click="$emit('pay', row)"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 px-2.5 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-bold transition flex items-center gap-1 font-tajawal cursor-pointer active:scale-95"
          :title="$t('contacts.collect_payment')"
          data-testid="action-pay"
        >
          <Receipt class="w-4 h-4 lg:w-3.5 lg:h-3.5" />
          <span class="hidden lg:inline">{{ $t('contacts.collect_payment') }}</span>
        </button>

        <router-link
          :to="`/customers/${row.id}/statement`"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 flex items-center justify-center p-2 text-slate-500 hover:text-theme-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition active:scale-95"
          :title="$t('contacts.statement')"
          data-testid="action-statement"
        >
          <FileText class="w-5 h-5 lg:w-4 lg:h-4" />
        </router-link>

        <button
          type="button"
          @click="$emit('edit', row)"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-cyan-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer active:scale-95"
          :title="$t('common.edit')"
          data-testid="action-edit"
        >
          <Pencil class="w-5 h-5 lg:w-4 lg:h-4" />
        </button>

        <button
          type="button"
          @click="$emit('delete', row)"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-2 text-slate-500 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition cursor-pointer active:scale-95"
          :title="$t('common.delete')"
          data-testid="action-delete"
        >
          <Trash2 class="w-5 h-5 lg:w-4 lg:h-4" />
        </button>
      </div>
    </template>
  </DataTable>
</template>
