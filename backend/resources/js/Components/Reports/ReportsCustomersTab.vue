<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl font-tajawal">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $t('reports.top_customers_title') }}</h3>
      <span class="text-xs text-slate-500 font-mono">{{ $t('reports.top_50_customers_sub') }}</span>
    </div>

    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="6" :cols="7" />
    </div>

    <div v-else-if="customerSales.length > 0">
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
              <th class="p-3 text-start font-bold">{{ $t('contacts.customer') }}</th>
              <th class="p-3 text-start font-bold">{{ $t('contacts.phone') }}</th>
              <th class="p-3 text-center font-bold">{{ $t('invoices.invoices_count_label') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.total_bought') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('invoices.paid') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('reports.remaining_in_period') }}</th>
              <th class="p-3 text-end font-bold">{{ $t('contacts.current_balance') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 font-sans">
            <tr v-for="c in customerSales" :key="c.customer_id" class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white font-tajawal">{{ c.name }}</td>
              <td class="p-3 font-mono text-slate-500 dark:text-slate-400">{{ c.phone || '—' }}</td>
              <td class="p-3 text-center font-mono font-bold text-slate-700 dark:text-slate-300">{{ c.total_invoices }}</td>
              <td class="p-3 text-end font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(c.total_bought) }}</td>
              <td class="p-3 text-end font-mono text-emerald-500 dark:text-emerald-400">{{ formatMoney(c.total_paid) }}</td>
              <td class="p-3 text-end font-mono text-theme-primary">{{ formatMoney(c.total_debt_in_period) }}</td>
              <td class="p-3 text-end font-mono font-bold" :class="c.current_balance > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-emerald-500 dark:text-emerald-400'">
                {{ formatMoney(c.current_balance) }} {{ $t('common.currency') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Tactile Cards -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="c in customerSales"
          :key="c.customer_id"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2"
        >
          <div class="flex items-center justify-between gap-2">
            <div>
              <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ c.name }}</h4>
              <span class="text-xs font-mono text-slate-400">{{ c.phone || '—' }}</span>
            </div>
            <div class="text-end">
              <span class="text-xs font-bold block text-slate-500">{{ $t('contacts.current_balance') }}</span>
              <span class="text-sm font-black font-mono" :class="c.current_balance > 0 ? 'text-rose-500' : 'text-emerald-500'">{{ formatMoney(c.current_balance) }} {{ $t('common.currency') }}</span>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span class="text-slate-500">{{ $t('reports.total_bought') }}: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ formatMoney(c.total_bought) }}</span></span>
            <span class="text-emerald-500 font-mono font-bold">{{ $t('invoices.paid') }}: {{ formatMoney(c.total_paid) }}</span>
          </div>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('reports.no_data_title')"
      :description="$t('reports.no_data_desc')"
      icon="👥"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  customerSales: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>
