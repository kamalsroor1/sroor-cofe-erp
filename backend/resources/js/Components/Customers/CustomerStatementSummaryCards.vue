<template>
  <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4" data-testid="statement-summary">
    <!-- Opening Balance -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.opening_balance') }}
      </div>
      <div data-testid="statement-opening-balance" class="text-xl font-black text-slate-900 dark:text-white font-mono">
        {{ formatMoney(summary.opening_balance || 0) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>

    <!-- Total Debit -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.period_debit') }} ({{ $t('contacts.withdrawals') }})
      </div>
      <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
        {{ formatMoney(summary.total_debit || 0) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>

    <!-- Total Credit -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.period_credit') }} ({{ $t('contacts.payments_received') }})
      </div>
      <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
        {{ formatMoney(summary.total_credit || 0) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>

    <!-- Closing Balance -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.closing_balance') }} ({{ $t('contacts.net_balance') }})
      </div>
      <div
        data-testid="statement-closing-balance"
        class="text-xl font-black font-mono"
        :class="closing > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-emerald-500 dark:text-emerald-400'"
      >
        {{ formatMoney(closing) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
      <div v-if="differsFromCurrent" class="text-[11px] text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.statement_current_balance_note', { amount: formatMoney(currentBalance) }) }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney, currencyLabel } = useFormatters();

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  currentBalance: { type: [Number, String], default: '0.000' },
  loading: { type: Boolean, default: false },
});

// The closing balance is the API's (opening + period movements), never a client-side sum.
const closing = computed(() => props.summary.closing_balance ?? '0.000');
const differsFromCurrent = computed(() => Number(closing.value) !== Number(props.currentBalance));
</script>
