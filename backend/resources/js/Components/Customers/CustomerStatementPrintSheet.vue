<template>
  <section
    v-if="rows.length > 0"
    data-testid="statement-print-sheet"
    class="hidden print:block text-black bg-white font-tajawal text-[11px]"
  >
    <div class="mb-3 space-y-1">
      <h1 class="text-lg font-black">{{ $t('contacts.statement_print_title') }}</h1>
      <p class="font-bold">
        {{ customer?.name }}
        <span v-if="customer?.phone" class="font-mono">({{ customer.phone }})</span>
      </p>
      <p>{{ $t('contacts.statement_period_label') }}: {{ periodText }}</p>
    </div>

    <table class="w-full border-collapse text-start">
      <thead>
        <tr class="border-b-2 border-slate-700">
          <th class="py-1 px-1.5 text-start">{{ $t('common.date') }}</th>
          <th class="py-1 px-1.5 text-start">{{ $t('contacts.transaction_type') }}</th>
          <th class="py-1 px-1.5 text-start">{{ $t('contacts.reference_no') }}</th>
          <th class="py-1 px-1.5 text-end">{{ $t('contacts.period_debit') }}</th>
          <th class="py-1 px-1.5 text-end">{{ $t('contacts.period_credit') }}</th>
          <th class="py-1 px-1.5 text-end">{{ $t('contacts.closing_balance') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, i) in rows"
          :key="i"
          class="border-b border-slate-300 break-inside-avoid"
          :class="row._opening ? 'italic font-bold' : ''"
        >
          <td class="py-1 px-1.5 font-mono">{{ row.date }}</td>
          <td class="py-1 px-1.5">{{ row.type }}</td>
          <td class="py-1 px-1.5 font-mono">{{ row.ref_number || '' }}</td>
          <td class="py-1 px-1.5 text-end font-mono">{{ row._opening ? '' : formatMoney(row.debit) }}</td>
          <td class="py-1 px-1.5 text-end font-mono">{{ row._opening ? '' : formatMoney(row.credit) }}</td>
          <td class="py-1 px-1.5 text-end font-mono font-bold">{{ formatMoney(row.balance_after) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-slate-700 font-black">
          <td class="py-1.5 px-1.5" colspan="3">{{ $t('common.total') }}</td>
          <td class="py-1.5 px-1.5 text-end font-mono">{{ formatMoney(summary.total_debit || 0) }}</td>
          <td class="py-1.5 px-1.5 text-end font-mono">{{ formatMoney(summary.total_credit || 0) }}</td>
          <td class="py-1.5 px-1.5 text-end font-mono" data-testid="statement-print-closing">
            {{ formatMoney(summary.closing_balance || 0) }} {{ currencyLabel }}
          </td>
        </tr>
      </tfoot>
    </table>
  </section>
</template>

<script setup>
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

defineProps({
  customer: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  periodText: { type: String, default: '' },
});
</script>
