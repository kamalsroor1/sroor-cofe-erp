<template>
  <div class="bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4 font-tajawal no-print">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3">
      <div class="flex items-center gap-2">
        <span class="text-base">📦</span>
        <h3 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t('invoices.items_count') }} ({{ items.length }})
        </h3>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-start text-xs border-collapse">
        <thead class="bg-slate-50 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
          <tr>
            <th class="py-3 px-3 text-start w-12">#</th>
            <th class="py-3 px-3 text-start">{{ $t('invoices.item_code_col') }}</th>
            <th class="py-3 px-3 text-start">{{ $t('invoices.item_name_col') }}</th>
            <th class="py-3 px-3 text-center">{{ $t('invoices.item_unit_col') }}</th>
            <th class="py-3 px-3 text-center">{{ $t('invoices.item_qty_col') }}</th>
            <th class="py-3 px-3 text-end">{{ $t('invoices.item_price_col') }}</th>
            <th class="py-3 px-3 text-end">{{ $t('invoices.item_discount_col') }}</th>
            <th class="py-3 px-3 text-end">{{ $t('invoices.item_total_col') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
          <tr
            v-for="(item, idx) in items"
            :key="item.id || idx"
            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
          >
            <td class="py-3 px-3 text-slate-400 font-bold text-[11px]">{{ idx + 1 }}</td>
            <td class="py-3 px-3">
              <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono text-[11px] font-bold">
                {{ item.item_code || `ITM-${item.item_id || item.id}` }}
              </span>
            </td>
            <td class="py-3 px-3 font-sans font-bold text-slate-900 dark:text-white text-xs">
              {{ item.name || item.item_name }}
            </td>
            <td class="py-3 px-3 text-center font-sans text-slate-500 dark:text-slate-400 text-xs">
              {{ item.unit || $t('common.unit_piece') }}
            </td>
            <td class="py-3 px-3 text-center font-black text-slate-900 dark:text-white">
              {{ formatMoney(item.quantity) }}
            </td>
            <td class="py-3 px-3 text-end font-bold text-slate-700 dark:text-slate-300">
              {{ formatMoney(item.unit_price) }}
            </td>
            <td class="py-3 px-3 text-end font-bold text-rose-500">
              {{ parseFloat(item.discount_amount || 0) > 0 ? '-' + formatMoney(item.discount_amount) : '—' }}
            </td>
            <td class="py-3 px-3 text-end font-black text-theme-primary text-sm">
              {{ formatMoney(item.total_price) }} {{ $t('common.currency') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { useFormatters } from '../../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  items: { type: Array, default: () => [] },
});
</script>
