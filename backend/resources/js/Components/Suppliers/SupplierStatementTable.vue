<template>
  <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <!-- Loading State -->
    <div v-if="loading" class="p-6">
      <TableSkeleton :rows="8" :cols="8" />
    </div>

    <!-- Data Loaded -->
    <div v-else-if="ledger.length > 0">
      <!-- 1. Desktop & Tablet Table (hidden md:block) -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-start text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 font-tajawal">
              <th class="py-3 px-4 text-start font-bold">#</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.date') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.transaction_type') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('contacts.reference_no') }}</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.period_credit') }} ({{ $t('contacts.withdrawals') }})</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.period_debit') }} ({{ $t('contacts.payments_received') }})</th>
              <th class="py-3 px-4 text-end font-bold">{{ $t('contacts.closing_balance') }}</th>
              <th class="py-3 px-4 text-start font-bold">{{ $t('common.notes') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60 font-sans">
            <tr
              v-for="(row, idx) in ledger"
              :key="idx"
              class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
            >
              <td class="py-3.5 px-4 font-mono text-slate-500">{{ idx + 1 }}</td>
              <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-300">{{ row.date }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white font-tajawal">{{ row.type }}</td>
              <td class="py-3.5 px-4 font-mono text-theme-primary">{{ row.ref_number || '—' }}</td>
              <td class="py-3.5 px-4 text-end font-mono font-bold" :class="row.credit > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                {{ formatMoney(row.credit) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-bold" :class="row.debit > 0 ? 'text-emerald-500 dark:text-emerald-400' : 'text-slate-400'">
                {{ formatMoney(row.debit) }}
              </td>
              <td class="py-3.5 px-4 text-end font-mono font-black" :class="row.balance_after > 0 ? 'text-theme-primary' : 'text-emerald-500 dark:text-emerald-400'">
                {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
              </td>
              <td class="py-3.5 px-4 font-tajawal text-slate-500 dark:text-slate-400 max-w-xs truncate">{{ row.notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 2. Mobile Responsive Tactile Cards (block md:hidden) -->
      <div class="block md:hidden divide-y divide-slate-200 dark:divide-slate-800 p-2 space-y-2">
        <div
          v-for="(row, idx) in ledger"
          :key="idx"
          class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5"
        >
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-bold text-slate-900 dark:text-white font-tajawal">{{ row.type }}</span>
            <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">{{ row.date }}</span>
          </div>

          <div v-if="row.ref_number" class="text-xs font-mono text-theme-primary">
            {{ $t('contacts.reference_no') }}: {{ row.ref_number }}
          </div>

          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs font-mono">
            <div>
              <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_credit') }}</span>
              <span class="font-bold" :class="row.credit > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-400'">
                {{ formatMoney(row.credit) }}
              </span>
            </div>
            <div>
              <span class="text-[10px] text-slate-400 block font-tajawal">{{ $t('contacts.period_debit') }}</span>
              <span class="font-bold text-emerald-500">
                {{ formatMoney(row.debit) }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span class="font-bold text-slate-500 font-tajawal">{{ $t('contacts.closing_balance') }}:</span>
            <span class="font-mono font-black" :class="row.balance_after > 0 ? 'text-theme-primary' : 'text-emerald-500'">
              {{ formatMoney(row.balance_after) }} {{ $t('common.currency') }}
            </span>
          </div>

          <div v-if="row.notes" class="text-[11px] text-slate-400 font-tajawal pt-1">
            {{ row.notes }}
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else
      :title="$t('contacts.no_transactions_found')"
      :description="$t('contacts.no_transactions_in_period')"
      icon="📄"
    />
  </div>
</template>

<script setup>
import TableSkeleton from '../Common/Skeletons/TableSkeleton.vue';
import EmptyState from '../Common/EmptyState.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  ledger: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>
