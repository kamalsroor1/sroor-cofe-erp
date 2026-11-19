<script setup>
import { computed } from 'vue';
import { useTrans } from '@/Composables/useTrans';
import { useFormatters } from '@/Composables/useFormatters';
import { dCmp } from '@/helpers/decimal';

/**
 * SHOP-02: A4 print of a session. `sheet` = the count sheet with an empty column to fill by hand
 * (system quantity omitted for a blind count); `variance` = the differences with their values.
 */
const props = defineProps({
  type: { type: String, default: 'sheet' },
  count: { type: Object, required: true },
  variance: { type: Object, default: null },
});

const { t } = useTrans();
const { formatQty, formatMoney, formatDateTime } = useFormatters();

const isVariance = computed(() => props.type === 'variance');
const hideSystem = computed(() => props.count.is_blind && props.count.is_open);
const title = computed(() =>
  isVariance.value ? t('stock_counts.variance_title') : t('stock_counts.sheet_print_title')
);
const printedAt = new Date().toISOString();

const signed = (value) => (dCmp(value, '0') > 0 ? `+${formatQty(value)}` : formatQty(value));
const money = (value) => (value === null || value === undefined ? '—' : formatMoney(value));
</script>

<template>
  <article
    class="mx-auto w-full max-w-[210mm] bg-white text-slate-900 p-6 sm:p-10 shadow-xl rounded-lg print:shadow-none print:rounded-none print:p-0 font-tajawal text-sm"
    data-testid="stock-count-print"
  >
    <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-slate-800 pb-4 mb-4">
      <div>
        <h1 class="text-xl font-black">{{ title }}</h1>
        <p class="text-xs text-slate-600 mt-1">
          {{ count.store_name }} · {{ count.category_name || t('stock_counts.all_categories') }}
        </p>
      </div>
      <dl class="text-xs grid grid-cols-[auto_auto] gap-x-3 gap-y-1">
        <dt class="font-bold">{{ t('stock_counts.number') }}</dt>
        <dd class="font-mono">{{ count.count_number }}</dd>
        <dt class="font-bold">{{ t('stock_counts.status_label') }}</dt>
        <dd>{{ count.status_label }}</dd>
        <dt class="font-bold">{{ t('stock_counts.created_at') }}</dt>
        <dd class="font-mono">{{ formatDateTime(count.created_at) }}</dd>
        <template v-if="count.posted_at">
          <dt class="font-bold">{{ t('stock_counts.posted_at') }}</dt>
          <dd class="font-mono">{{ formatDateTime(count.posted_at) }}</dd>
        </template>
        <dt class="font-bold">{{ t('stock_counts.printed_at') }}</dt>
        <dd class="font-mono">{{ formatDateTime(printedAt) }}</dd>
      </dl>
    </header>

    <!-- Count sheet -->
    <table v-if="!isVariance" class="w-full border-collapse text-xs">
      <thead>
        <tr class="bg-slate-100">
          <th class="border border-slate-400 p-1.5 w-8">{{ t('stock_counts.serial') }}</th>
          <th class="border border-slate-400 p-1.5 text-start">{{ t('stock_counts.item') }}</th>
          <th class="border border-slate-400 p-1.5">{{ t('stock_counts.code') }}</th>
          <th class="border border-slate-400 p-1.5">{{ t('stock_counts.unit') }}</th>
          <th v-if="!hideSystem" class="border border-slate-400 p-1.5">{{ t('stock_counts.system_qty') }}</th>
          <th class="border border-slate-400 p-1.5 w-28">{{ t('stock_counts.counted_column') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(line, idx) in count.lines || []" :key="line.item_id" class="break-inside-avoid">
          <td class="border border-slate-400 p-1.5 text-center font-mono">{{ idx + 1 }}</td>
          <td class="border border-slate-400 p-1.5 font-bold">{{ line.item_name }}</td>
          <td class="border border-slate-400 p-1.5 text-center font-mono">{{ line.item_code }}</td>
          <td class="border border-slate-400 p-1.5 text-center">{{ line.unit }}</td>
          <td v-if="!hideSystem" class="border border-slate-400 p-1.5 text-center font-mono">
            {{ formatQty(line.system_qty) }}
          </td>
          <td class="border border-slate-400 p-1.5 text-center font-mono">
            {{ count.is_open || line.counted_qty === null ? '' : formatQty(line.counted_qty) }}
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Variance report -->
    <template v-else-if="variance">
      <section class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4 text-xs">
        <div class="border border-slate-300 rounded p-2">
          <p class="text-slate-500">{{ t('stock_counts.counted_total') }}</p>
          <p class="font-mono font-bold">{{ variance.totals.counted_count }} / {{ variance.totals.lines_count }}</p>
        </div>
        <div class="border border-slate-300 rounded p-2">
          <p class="text-slate-500">{{ t('stock_counts.matched_total') }}</p>
          <p class="font-mono font-bold">{{ variance.totals.matched_count }}</p>
        </div>
        <div class="border border-slate-300 rounded p-2">
          <p class="text-slate-500">{{ t('stock_counts.surplus_value') }} ({{ variance.totals.surplus_count }})</p>
          <p class="font-mono font-bold text-emerald-700">{{ money(variance.totals.surplus_value) }}</p>
        </div>
        <div class="border border-slate-300 rounded p-2">
          <p class="text-slate-500">{{ t('stock_counts.shortage_value') }} ({{ variance.totals.shortage_count }})</p>
          <p class="font-mono font-bold text-rose-700">{{ money(variance.totals.shortage_value) }}</p>
        </div>
      </section>
      <p v-if="count.is_open" class="text-[11px] text-slate-500 mb-2">{{ t('stock_counts.expected_live_hint') }}</p>

      <table v-if="variance.lines.length" class="w-full border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100">
            <th class="border border-slate-400 p-1.5 w-8">{{ t('stock_counts.serial') }}</th>
            <th class="border border-slate-400 p-1.5 text-start">{{ t('stock_counts.item') }}</th>
            <th class="border border-slate-400 p-1.5">{{ t('stock_counts.expected_qty') }}</th>
            <th class="border border-slate-400 p-1.5">{{ t('stock_counts.counted_qty') }}</th>
            <th class="border border-slate-400 p-1.5">{{ t('stock_counts.difference') }}</th>
            <th class="border border-slate-400 p-1.5">{{ t('stock_counts.unit_cost') }}</th>
            <th class="border border-slate-400 p-1.5">{{ t('stock_counts.difference_value') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, idx) in variance.lines" :key="line.item_id" class="break-inside-avoid">
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ idx + 1 }}</td>
            <td class="border border-slate-400 p-1.5">
              <span class="font-bold">{{ line.item_name }}</span>
              <span class="text-slate-500 font-mono text-[10px]"> · {{ line.item_code }} · {{ line.unit }}</span>
            </td>
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ formatQty(line.expected_qty) }}</td>
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ formatQty(line.counted_qty) }}</td>
            <td
              class="border border-slate-400 p-1.5 text-center font-mono font-bold"
              :class="dCmp(line.difference, '0') > 0 ? 'text-emerald-700' : 'text-rose-700'"
            >
              {{ signed(line.difference) }}
            </td>
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ money(line.unit_cost) }}</td>
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ money(line.difference_value) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="bg-slate-100 font-bold">
            <td colspan="6" class="border border-slate-400 p-1.5 text-end">{{ t('stock_counts.net_value') }}</td>
            <td class="border border-slate-400 p-1.5 text-center font-mono">{{ money(variance.totals.net_value) }}</td>
          </tr>
        </tfoot>
      </table>
      <p v-else class="text-center py-6 text-slate-500">{{ t('stock_counts.no_differences') }}</p>
    </template>

    <footer class="grid grid-cols-2 gap-8 mt-10 text-xs break-inside-avoid">
      <div class="border-t border-slate-500 pt-2 text-center">{{ t('stock_counts.signature_counter') }}</div>
      <div class="border-t border-slate-500 pt-2 text-center">{{ t('stock_counts.signature_reviewer') }}</div>
    </footer>
  </article>
</template>
