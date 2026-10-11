<template>
  <DataTable
    :rows="invoices"
    :columns="columns"
    :loading="isLoading"
    :pagination="pagination"
    :empty-title="$t('invoices.no_invoices_found')"
    :empty-message="$t('invoices.no_invoices_description')"
    :empty-icon="Receipt"
    @page-change="$emit('change-page', $event)"
    data-testid="invoices-table"
  >
    <template #cell-checkbox="{ row }">
      <input
        type="checkbox"
        :value="row.id"
        :checked="selectedIds.includes(row.id)"
        @change="$emit('toggle-select', row.id)"
        class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
      />
    </template>
    <template #header-checkbox>
      <input
        type="checkbox"
        :checked="isAllSelected"
        @change="$emit('toggle-select-all')"
        class="w-4 h-4 text-theme-primary rounded border-slate-300 dark:border-slate-700 focus:ring-theme-primary cursor-pointer"
      />
    </template>

    <template #cell-invoice_number="{ row }">
      <div class="font-mono font-bold text-theme-primary">
        <button type="button" @click="$emit('preview', row)" class="hover:underline cursor-pointer font-bold">
          {{ row.invoice_number }}
        </button>
      </div>
    </template>

    <template #cell-customer="{ row }">
      <div class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.customer_name }}</div>
      <div v-if="row.customer_phone" class="text-[10px] text-slate-400 font-mono">
        {{ row.customer_phone }}
      </div>
    </template>

    <template #cell-date="{ row }">
      <span class="font-mono text-slate-500 text-[11px] whitespace-nowrap">{{ row.invoice_date }}</span>
    </template>

    <template #cell-payment_method="{ row }">
      <span
        class="px-2 py-0.5 rounded-lg text-[11px] font-bold font-tajawal bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
      >
        {{ formatPaymentType(row.payment_type) }}
      </span>
    </template>

    <template #cell-net_total="{ row }">
      <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
        {{ formatMoney(row.net_total) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-paid_amount="{ row }">
      <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
        {{ formatMoney(row.paid_amount) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-remaining_amount="{ row }">
      <span class="font-mono font-bold" :class="row.remaining_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
        {{ formatMoney(row.remaining_amount) }} {{ $t('common.currency') }}
      </span>
    </template>

    <template #cell-status="{ row }">
      <span
        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-block font-tajawal"
        :class="
          !row.is_cancelled
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500'
            : 'bg-rose-500/10 border-rose-500/30 text-rose-400'
        "
      >
        {{ !row.is_cancelled ? $t('invoices.confirmed_badge') : $t('invoices.cancelled_badge') }}
      </span>
    </template>

    <template #cell-actions="{ row }">
      <div class="flex items-center justify-center gap-1">
        <button
          type="button"
          @click="$emit('preview', row)"
          data-testid="action-preview"
          class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 p-1.5 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-950/40 rounded-xl transition cursor-pointer"
          :title="$t('invoices.view_details')"
        >
          <Eye class="w-4 h-4" />
        </button>

        <ActionMenu
          :items="getInvoiceActions(row)"
          :title="$t('invoices.invoice_with_number', { number: row.invoice_number })"
          button-class="min-h-[44px] min-w-[44px] lg:min-h-0 lg:min-w-0 h-8 w-8 lg:min-w-[32px] p-0"
        />
      </div>
    </template>
  </DataTable>
</template>

<script setup>
import { computed } from 'vue';
import { Eye, Printer, Ban, Receipt } from 'lucide-vue-next';
import DataTable from '@/Components/Common/DataTable.vue';
import ActionMenu from '../ActionMenu.vue';
import { useFormatters } from '../../Composables/useFormatters';
import { trans } from '../../helpers/trans';
import { useTrans } from '../../Composables/useTrans';

const { t } = useTrans();
const { formatMoney } = useFormatters();

const props = defineProps({
  invoices: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  pagination: {
    type: Object,
    default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }),
  },
  isLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-select', 'toggle-select-all', 'preview', 'print', 'cancel', 'change-page']);

const isAllSelected = computed(() => {
  return props.invoices.length > 0 && props.invoices.every((inv) => props.selectedIds.includes(inv.id));
});

const formatPaymentType = (type) => {
  const map = {
    cash: trans('invoices.payment_cash'),
    credit: trans('invoices.payment_credit'),
    partial: trans('invoices.payment_partial'),
    card: trans('invoices.payment_card'),
    e_wallet: trans('invoices.payment_ewallet'),
    instapay: trans('invoices.payment_instapay'),
  };
  return map[type] || type || trans('invoices.payment_cash');
};

const getInvoiceActions = (inv) => [
  {
    label: trans('invoices.view_details'),
    icon: Eye,
    onClick: () => emit('preview', inv),
  },
  {
    label: trans('invoices.print_cashier_receipt'),
    icon: Printer,
    variant: 'success',
    onClick: () => emit('print', inv.id),
  },
  {
    label: trans('invoices.cancel_invoice'),
    icon: Ban,
    variant: 'danger',
    show: !inv.is_cancelled,
    onClick: () => emit('cancel', inv),
  },
];

const columns = computed(() => [
  { key: 'checkbox', label: '', align: 'center', width: 'w-10' },
  { key: 'invoice_number', label: t('invoices.invoice_number'), align: 'start' },
  { key: 'customer', label: t('invoices.customer'), align: 'start' },
  { key: 'date', label: t('invoices.date'), align: 'start' },
  { key: 'payment_method', label: t('invoices.payment_method'), align: 'center' },
  { key: 'net_total', label: t('invoices.total_net'), align: 'end' },
  { key: 'paid_amount', label: t('invoices.paid'), align: 'end' },
  { key: 'remaining_amount', label: t('invoices.remaining'), align: 'end' },
  { key: 'status', label: t('invoices.status'), align: 'center' },
  { key: 'actions', label: t('common.actions'), align: 'center' },
]);
</script>
