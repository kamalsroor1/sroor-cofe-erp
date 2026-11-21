<template>
  <section
    data-testid="report-print-sheet"
    :data-rows="sheet.rows.length"
    class="hidden print:block text-black bg-white font-tajawal text-[11px]"
  >
    <div class="mb-3 space-y-1">
      <h1 class="text-lg font-black">{{ sheet.title }}</h1>
      <p v-if="periodText">{{ $t('reports.print_sheet.period') }}: {{ periodText }}</p>
      <p>{{ $t('reports.print_sheet.scope') }}: {{ scopeText }}</p>
      <p>{{ $t('reports.print_rows_count', { count: sheet.rows.length }) }}</p>
    </div>

    <dl v-if="sheet.summary.length" class="mb-3 grid grid-cols-3 gap-2">
      <div v-for="line in sheet.summary" :key="line.label" class="border border-slate-400 rounded p-1.5">
        <dt class="font-bold">{{ line.label }}</dt>
        <dd class="font-mono">{{ formatMoney(line.value) }} {{ currencyLabel }}</dd>
      </div>
    </dl>

    <table class="w-full border-collapse text-start">
      <thead>
        <tr class="border-b-2 border-slate-700">
          <th
            v-for="col in sheet.columns"
            :key="col.key"
            class="py-1 px-1.5"
            :class="isTextual(col) ? 'text-start' : 'text-end'"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in sheet.rows" :key="i" class="border-b border-slate-300 break-inside-avoid">
          <td
            v-for="col in sheet.columns"
            :key="col.key"
            class="py-1 px-1.5"
            :class="[isTextual(col) ? 'text-start' : 'text-end font-mono', col.strong ? 'font-bold' : '']"
          >
            {{ cell(col, row) }}
          </td>
        </tr>
      </tbody>
      <tfoot v-if="sheet.totals">
        <tr class="border-t-2 border-slate-700 font-black" data-testid="report-print-totals">
          <td
            v-for="(col, index) in sheet.columns"
            :key="col.key"
            class="py-1.5 px-1.5"
            :class="isTextual(col) ? 'text-start' : 'text-end font-mono'"
          >
            {{ index === 0 ? $t('reports.totals_row_label') : totalCell(col, sheet.totals) }}
          </td>
        </tr>
      </tfoot>
    </table>
  </section>
</template>

<script setup>
import { useFormatters } from '../../Composables/useFormatters';
import { reportPrintValue } from '../../helpers/reportPrint';

const { formatMoney, formatQty, currencyLabel } = useFormatters();

defineProps({
  // { title, columns, rows, totals, summary } from useReportPrint.
  sheet: { type: Object, required: true },
  periodText: { type: String, default: '' },
  scopeText: { type: String, default: '' },
});

const isTextual = (col) => col.type === 'text' || col.type === 'mono';

const format = (col, value, row = null) => {
  if (value === null || value === undefined || value === '') return '—';
  switch (col.type) {
    case 'money':
      return col.blankZero && Number(value) === 0 ? '—' : formatMoney(value);
    case 'qty':
      return [formatQty(value), col.unitKey && row ? row[col.unitKey] : ''].filter(Boolean).join(' ');
    case 'int':
      return value;
    case 'percent':
      return `${value}%`;
    default:
      return value;
  }
};

const cell = (col, row) => format(col, reportPrintValue(col, row), row);

// Totals rows carry the same keys as the columns; textual columns stay blank.
const totalCell = (col, totals) => {
  if (isTextual(col)) return '';
  return format(col, totals?.[col.key]);
};
</script>
