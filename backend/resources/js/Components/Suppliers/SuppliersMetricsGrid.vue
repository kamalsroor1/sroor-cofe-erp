<template>
  <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <StatCardSkeleton v-for="i in 3" :key="i" />
  </div>

  <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <!-- Total Payables -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('contacts.total_payables') }}</span>
        <div class="w-8 h-8 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center">
          <TrendingDown class="w-4 h-4" />
        </div>
      </div>
      <div class="text-2xl font-black text-theme-primary font-mono">
        {{ formatMoney(metrics.total_payable || 0) }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('common.currency') }}</span>
      </div>
      <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
        {{ $t('contacts.total_payables_sub') }}
      </div>
    </div>

    <!-- Creditors Count -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('contacts.creditors_count') }}</span>
        <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center">
          <AlertCircle class="w-4 h-4" />
        </div>
      </div>
      <div class="text-2xl font-black text-rose-500 font-mono">
        {{ metrics.creditors_count || 0 }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('contacts.supplier_unit') }}</span>
      </div>
      <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
        {{ $t('contacts.creditors_count_sub') }}
      </div>
    </div>

    <!-- Total Suppliers Count -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-lg space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-tajawal">{{ $t('contacts.total_suppliers_count') }}</span>
        <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
          <Factory class="w-4 h-4" />
        </div>
      </div>
      <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
        {{ metrics.total_suppliers || 0 }} <span class="text-xs text-slate-400 font-normal font-tajawal">{{ $t('contacts.supplier_unit') }}</span>
      </div>
      <div class="text-[11px] text-slate-500 dark:text-slate-400 font-bold font-tajawal">
        {{ $t('contacts.total_suppliers_sub') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { TrendingDown, AlertCircle, Factory } from 'lucide-vue-next';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import { useFormatters } from '../../Composables/useFormatters';

const { formatMoney } = useFormatters();

defineProps({
  metrics: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
