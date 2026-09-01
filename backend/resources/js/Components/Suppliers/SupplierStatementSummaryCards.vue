<template>
  <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <StatCardSkeleton v-for="i in 3" :key="i" />
  </div>

  <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <!-- Total Purchases (Credit) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.total_purchases_label') }} ({{ $t('contacts.withdrawals') }}/{{ $t('contacts.credit_balance') }})
      </div>
      <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
        {{ formatMoney(summary.total_purchases || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
    </div>

    <!-- Total Paid (Debit) -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.total_payments_label') }} ({{ $t('contacts.payments_received') }}/{{ $t('contacts.debt_due') }})
      </div>
      <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
        {{ formatMoney(summary.total_paid || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
    </div>

    <!-- Closing Balance -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1">
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.closing_balance') }} ({{ $t('contacts.due_to_supplier') }})
      </div>
      <div
        class="text-xl font-black font-mono"
        :class="currentBalance > 0 ? 'text-theme-primary' : 'text-emerald-500 dark:text-emerald-400'"
      >
        {{ formatMoney(currentBalance) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  summary: { type: Object, default: () => ({}) },
  currentBalance: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});
</script>
