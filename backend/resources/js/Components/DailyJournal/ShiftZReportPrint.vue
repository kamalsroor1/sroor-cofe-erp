<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  report: { type: Object, default: null },
});

const { formatMoney, formatDateTime, currencyLabel } = useFormatters();

const isProvisional = computed(() => props.report?.is_provisional === true);

const header = computed(() => {
  const r = props.report || {};
  return [
    { key: 'store', label: 'common.store', value: r.store_name },
    { key: 'number', label: 'treasury.shift_number', value: r.shift_number },
    { key: 'cashier', label: 'treasury.cashier_label', value: r.cashier_name },
    { key: 'opened', label: 'treasury.opened_at_label', value: r.opened_at ? formatDateTime(r.opened_at) : '' },
    { key: 'closed', label: 'treasury.closed_at_label', value: r.closed_at ? formatDateTime(r.closed_at) : '' },
    {
      key: 'printed',
      label: 'treasury.print_time',
      value: isProvisional.value && r.generated_at ? formatDateTime(r.generated_at) : '',
    },
  ].filter((row) => row.value);
});

const lines = computed(() => {
  const r = props.report || {};
  return [
    { key: 'opening', label: 'treasury.opening_float', value: r.opening_cash_balance },
    { key: 'cash-sales', label: 'treasury.cash_sales', value: r.total_cash_sales },
    { key: 'receipts', label: 'treasury.customer_collections', value: r.total_customer_receipts },
    { key: 'collected', label: 'treasury.total_cash_collected', value: r.total_payments_collected, strong: true },
    { key: 'expenses', label: 'treasury.cash_expenses', value: r.total_expenses, minus: true },
    { key: 'suppliers', label: 'treasury.supplier_cash_payments', value: r.total_supplier_paid, minus: true },
    { key: 'refunds', label: 'treasury.cash_refunds_label', value: r.total_refunds, minus: true },
    { key: 'expected', label: 'treasury.expected_in_drawer', value: r.expected_cash_balance, strong: true },
    { key: 'actual', label: 'treasury.actual_closing_cash', value: r.actual_cash_balance },
    { key: 'difference', label: 'treasury.cash_difference', value: r.cash_difference, strong: true },
  ]
    .filter((line) => line.value !== null && line.value !== undefined)
    .filter((line) => !(isProvisional.value && ['actual', 'difference'].includes(line.key)));
});
</script>

<template>
  <Teleport to="body">
    <section
      v-if="report"
      id="z-report-print-area"
      data-testid="z-report-print-area"
      class="hidden print:block bg-white text-black font-tajawal text-xs"
    >
      <h1 class="text-center text-base font-black pb-2 border-b-2 border-dashed border-black">
        {{ isProvisional ? $t('treasury.x_report_title') : $t('treasury.z_report_title') }}
      </h1>

      <p
        v-if="isProvisional"
        data-testid="x-report-provisional-note"
        class="my-2 p-2 border-2 border-black text-center font-black"
      >
        {{ $t('treasury.x_report_provisional_note') }}
      </p>

      <div class="py-2 border-b-2 border-dashed border-black space-y-1">
        <div v-for="row in header" :key="row.key" class="flex justify-between gap-2">
          <span class="font-bold">{{ $t(row.label) }}:</span>
          <span class="font-mono">{{ row.value }}</span>
        </div>
      </div>

      <div class="py-2 border-b-2 border-dashed border-black space-y-1">
        <div
          v-for="line in lines"
          :key="line.key"
          class="flex justify-between gap-2"
          :class="line.strong ? 'font-black border-t border-black pt-1' : ''"
        >
          <span>{{ $t(line.label) }}</span>
          <span class="font-mono" dir="ltr">
            {{ line.minus ? '-' : '' }}{{ formatMoney(line.value) }} {{ currencyLabel }}
          </span>
        </div>
      </div>

      <div
        v-if="report.total_credit_sales !== null && report.total_credit_sales !== undefined"
        class="py-2 flex justify-between gap-2 text-[11px]"
        :class="report.notes ? 'border-b-2 border-dashed border-black' : ''"
      >
        <span>{{ $t('treasury.recorded_credit_sales') }}</span>
        <span class="font-mono" dir="ltr">{{ formatMoney(report.total_credit_sales) }} {{ currencyLabel }}</span>
      </div>

      <div v-if="report.notes" class="pt-2">
        <span class="font-bold">{{ $t('common.notes') }}:</span>
        <span class="whitespace-pre-line">{{ report.notes }}</span>
      </div>
    </section>
  </Teleport>
</template>
