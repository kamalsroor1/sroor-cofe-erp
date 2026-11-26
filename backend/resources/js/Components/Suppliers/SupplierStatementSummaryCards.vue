<template>
  <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <StatCardSkeleton v-for="i in 3" :key="i" />
  </div>

  <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <!-- Total Purchases (Credit) -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.supplier_statement.total_purchases') }}
      </div>
      <div class="text-xl font-black text-slate-900 dark:text-white font-mono">
        {{ formatMoney(summary.total_purchases || 0) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>

    <!-- Total Paid (Debit) -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">
        {{ $t('contacts.supplier_statement.total_paid') }}
      </div>
      <div class="text-xl font-black text-emerald-500 dark:text-emerald-400 font-mono">
        {{ formatMoney(summary.total_paid || 0) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>

    <!-- Closing Balance -->
    <div
      class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md space-y-1"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{
          $t('contacts.supplier_statement.remaining')
        }}</span>
        <!-- UX-240: say which way the money goes instead of debit/credit -->
        <span
          data-testid="supplier-balance-direction"
          class="px-2 rounded-full border text-xs font-bold font-tajawal"
          :class="direction.class"
          >{{ $t(direction.key) }}</span
        >
      </div>
      <div
        class="text-xl font-black font-mono"
        :class="currentBalance > 0 ? 'text-theme-primary' : 'text-emerald-500 dark:text-emerald-400'"
      >
        {{ formatMoney(absAmount(currentBalance)) }}
        <span class="text-xs text-slate-400 font-normal font-tajawal">{{ currencyLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { computed } from 'vue';
import { useFormatters } from '../../Composables/useFormatters';
import { dCmp } from '../../helpers/decimal';

const { formatMoney, currencyLabel } = useFormatters();

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  currentBalance: { type: [Number, String], default: '0.000' },
  loading: { type: Boolean, default: false },
});

const absAmount = (value) => String(value ?? '0').replace(/^-/, '');

// Positive = still owed to the supplier, negative = the supplier owes the shop.
const direction = computed(() => {
  const sign = dCmp(String(props.currentBalance ?? '0'), '0');
  if (sign > 0)
    return {
      key: 'contacts.supplier_statement.you_owe',
      class: 'bg-amber-500/10 border-amber-500/40 text-amber-700 dark:text-amber-300',
    };
  if (sign < 0)
    return {
      key: 'contacts.supplier_statement.you_are_owed',
      class: 'bg-emerald-500/10 border-emerald-500/40 text-emerald-700 dark:text-emerald-300',
    };
  return {
    key: 'contacts.supplier_statement.settled',
    class: 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300',
  };
});
</script>
