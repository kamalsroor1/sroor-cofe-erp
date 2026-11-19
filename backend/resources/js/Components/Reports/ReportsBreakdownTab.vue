<template>
  <div
    class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal"
    :data-testid="`report-${reportKey}`"
  >
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ title }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ hint }}</p>
      </div>
      <button
        v-if="canExport"
        type="button"
        class="min-h-[44px] px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer active:scale-95 disabled:opacity-60 disabled:cursor-wait"
        :disabled="exporting"
        :data-testid="`export-${reportKey}`"
        @click="$emit('export')"
      >
        <Loader2 v-if="exporting" class="w-4 h-4 animate-spin" aria-hidden="true" />
        <Download v-else class="w-4 h-4" aria-hidden="true" />
        <span>{{ $t('reports.export_csv') }}</span>
      </button>
    </div>

    <div v-if="loading" class="p-4">
      <TableSkeleton :columns-count="Math.min(columns.length, 6)" :rows-count="6" />
    </div>

    <ErrorState v-else-if="error" :message="errorMessage" @retry="$emit('retry')" />

    <div v-else-if="rows.length > 0">
      <!-- Desktop table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr
              class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800"
            >
              <th
                v-for="col in columns"
                :key="col.key"
                class="p-3 font-bold whitespace-nowrap"
                :class="col.type === 'text' || col.type === 'mono' ? 'text-start' : 'text-end'"
              >
                {{ col.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50">
            <tr
              v-for="(row, index) in rows"
              :key="rowKey(row, index)"
              class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                class="p-3 whitespace-nowrap"
                :class="cellClass(col, row[col.key])"
              >
                <span v-if="col.colorKey" class="inline-flex items-center gap-2">
                  <span
                    class="w-2.5 h-2.5 rounded-full shrink-0 bg-slate-300 dark:bg-slate-600"
                    :style="row[col.colorKey] ? { backgroundColor: row[col.colorKey] } : null"
                    aria-hidden="true"
                  />
                  {{ format(col, row[col.key]) }}
                </span>
                <template v-else>{{ format(col, row[col.key]) }}</template>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="totals">
            <tr
              class="bg-slate-50 dark:bg-slate-900/80 border-t-2 border-slate-300 dark:border-slate-700 font-black"
              data-testid="report-totals-row"
            >
              <td
                v-for="(col, index) in columns"
                :key="col.key"
                class="p-3 whitespace-nowrap"
                :class="index === 0 ? 'text-start text-slate-900 dark:text-white' : cellClass(col, totals[col.key])"
              >
                {{ index === 0 ? $t('reports.csv_totals_row') : format(col, totals[col.key]) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="block md:hidden p-2 space-y-2">
        <div
          v-for="(row, index) in rows"
          :key="rowKey(row, index)"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
        >
          <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ row[primaryColumn.key] }}</h4>
          <div v-for="col in secondaryColumns" :key="col.key" class="flex items-center justify-between gap-3 text-xs">
            <span class="text-slate-500 dark:text-slate-400">{{ col.label }}</span>
            <span :class="cellClass(col, row[col.key])">{{ format(col, row[col.key]) }}</span>
          </div>
        </div>
        <div
          v-if="totals"
          class="p-4 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/80 space-y-2"
        >
          <h4 class="text-sm font-black text-slate-900 dark:text-white">{{ $t('reports.csv_totals_row') }}</h4>
          <div
            v-for="col in secondaryColumns.filter((c) => c.type !== 'mono')"
            :key="col.key"
            class="flex items-center justify-between gap-3 text-xs"
          >
            <span class="text-slate-500 dark:text-slate-400">{{ col.label }}</span>
            <span class="font-black" :class="cellClass(col, totals[col.key])">{{ format(col, totals[col.key]) }}</span>
          </div>
        </div>
      </div>

      <Pagination
        v-if="meta && Number(meta.last_page) > 1"
        :pagination="meta"
        class="px-4 pb-4"
        @page-change="$emit('page-change', $event)"
      />
    </div>

    <EmptyState v-else :title="$t('reports.no_data_title')" :description="$t('reports.no_data_desc')" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Download, Loader2 } from 'lucide-vue-next';
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import ErrorState from '../Common/ErrorState.vue';
import Pagination from '../Common/Pagination.vue';
import { useFormatters } from '../../Composables/useFormatters';

const props = defineProps({
  reportKey: { type: String, required: true },
  title: { type: String, default: '' },
  hint: { type: String, default: '' },
  columns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: null },
  meta: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  exporting: { type: Boolean, default: false },
  canExport: { type: Boolean, default: true },
});

defineEmits(['page-change', 'retry', 'export']);

const { formatMoney, formatQty } = useFormatters();

const primaryColumn = computed(() => props.columns.find((c) => c.primary) ?? props.columns[0] ?? { key: 'name' });
const secondaryColumns = computed(() => props.columns.filter((c) => c.key !== primaryColumn.value.key));

const rowKey = (row, index) => row.id ?? row.user_id ?? row.category_id ?? `row-${index}`;

const format = (col, value) => {
  if (value === null || value === undefined || value === '') return '—';
  switch (col.type) {
    case 'money':
      return formatMoney(value);
    case 'qty':
      return formatQty(value);
    case 'percent':
      return `${value}%`;
    default:
      return value;
  }
};

const TONES = {
  danger: 'text-rose-600 dark:text-rose-400',
  warning: 'text-amber-600 dark:text-amber-400',
  success: 'text-emerald-600 dark:text-emerald-400',
};

const cellClass = (col, value) => {
  const classes = [];
  if (col.type === 'text') classes.push('text-start font-bold text-slate-900 dark:text-white');
  else if (col.type === 'mono') classes.push('text-start font-mono text-slate-500 dark:text-slate-400');
  else classes.push('text-end font-mono');

  const isZero = value === null || value === undefined || Number(value) === 0;
  if (col.tone && !isZero) classes.push(TONES[col.tone]);
  else if (col.strong) classes.push('font-black text-theme-primary');
  else if (col.type !== 'text' && col.type !== 'mono') classes.push('text-slate-700 dark:text-slate-300');
  return classes;
};
</script>
