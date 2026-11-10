<script setup>
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import EmptyState from '../Common/EmptyState.vue';

const props = defineProps({
  type: { type: String, required: true },
  rows: { type: Array, required: true },
});

const { formatMoney, formatQty } = useFormatters();

const COLUMNS = {
  items: ['code', 'name', 'category', 'selling_price', 'opening_quantity'],
  customers: ['name', 'phone', 'price_tier', 'opening_balance'],
  suppliers: ['name', 'company_name', 'phone', 'opening_balance'],
  categories: ['name', 'icon'],
};
const MONEY = ['cost_price', 'selling_price', 'min_selling_price', 'opening_cost', 'opening_balance'];
const QTY = ['opening_quantity', 'min_stock_level'];

const columns = computed(() => COLUMNS[props.type] || []);

const display = (column, value) => {
  if (value === null || value === undefined || value === '') return '-';
  if (MONEY.includes(column)) return formatMoney(value);
  if (QTY.includes(column)) return formatQty(value);
  return String(value);
};

const badgeClass = (status) =>
  ({
    valid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
    invalid: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
    duplicate: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
  })[status] || '';
</script>

<template>
  <EmptyState v-if="rows.length === 0" :title="$t('import.ui.no_rows')" />
  <div v-else class="font-tajawal">
    <div
      class="hidden md:block overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
    >
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs font-black text-slate-500 dark:text-slate-400">
          <tr>
            <th class="px-3 py-3 text-start">{{ $t('import.ui.row_column') }}</th>
            <th v-for="column in columns" :key="column" class="px-3 py-3 text-start">
              {{ $t(`import.columns.${type}.${column}`) }}
            </th>
            <th class="px-3 py-3 text-start">{{ $t('import.ui.status_column') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <tr v-for="row in rows" :key="row.row_number" class="align-top">
            <td class="px-3 py-3 font-black tabular-nums text-slate-500">{{ row.row_number }}</td>
            <td v-for="column in columns" :key="column" class="px-3 py-3 text-slate-800 dark:text-slate-100 font-bold">
              {{ display(column, row.data?.[column]) }}
            </td>
            <td class="px-3 py-3 space-y-1">
              <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-black" :class="badgeClass(row.status)">
                {{ $t(`import.ui.status.${row.status}`) }}
              </span>
              <ul v-if="row.errors?.length" class="text-xs font-bold text-rose-600 dark:text-rose-400 space-y-0.5">
                <li v-for="(message, index) in row.errors" :key="index">{{ message }}</li>
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ul class="md:hidden space-y-3">
      <li
        v-for="row in rows"
        :key="row.row_number"
        class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-black text-slate-500 tabular-nums">{{
            $t('import.ui.row_number', { number: row.row_number })
          }}</span>
          <span class="px-2.5 py-1 rounded-lg text-[11px] font-black" :class="badgeClass(row.status)">
            {{ $t(`import.ui.status.${row.status}`) }}
          </span>
        </div>
        <dl class="grid grid-cols-2 gap-x-3 gap-y-2">
          <div v-for="column in columns" :key="column" class="min-w-0">
            <dt class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
              {{ $t(`import.columns.${type}.${column}`) }}
            </dt>
            <dd class="text-sm font-black text-slate-800 dark:text-slate-100 break-words">
              {{ display(column, row.data?.[column]) }}
            </dd>
          </div>
        </dl>
        <ul v-if="row.errors?.length" class="text-xs font-bold text-rose-600 dark:text-rose-400 space-y-0.5">
          <li v-for="(message, index) in row.errors" :key="index">{{ message }}</li>
        </ul>
      </li>
    </ul>
  </div>
</template>
