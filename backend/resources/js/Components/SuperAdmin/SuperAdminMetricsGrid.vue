<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 font-tajawal">
    <template v-if="loading">
      <StatCardSkeleton v-for="i in 5" :key="i" />
    </template>
    <template v-else>
      <!-- Total Tenants -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <span class="text-slate-500 dark:text-slate-400 text-xs font-bold">{{ $t('super.total_tenants') }}</span>
        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ metrics.total_tenants || 0 }}</div>
        <div class="text-[10px] text-slate-400">{{ $t('super.platform_accounts') }}</div>
      </div>

      <!-- Active Tenants -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold">{{ $t('super.active_tenants') }}</span>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ metrics.active_tenants || 0 }}</div>
        <div class="text-[10px] text-slate-400">{{ $t('super.active_subscriptions') }}</div>
      </div>

      <!-- Trial Tenants -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <span class="text-theme-primary text-xs font-bold">{{ $t('super.trial_tenants') }}</span>
        <div class="text-2xl font-black text-theme-primary font-mono">{{ metrics.trial_tenants || 0 }}</div>
        <div class="text-[10px] text-slate-400">{{ $t('super.under_trial') }}</div>
      </div>

      <!-- Suspended Tenants -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1">
        <span class="text-rose-600 dark:text-rose-400 text-xs font-bold">{{ $t('super.suspended_tenants') }}</span>
        <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">{{ metrics.suspended_tenants || 0 }}</div>
        <div class="text-[10px] text-slate-400">{{ $t('super.suspended_or_expired') }}</div>
      </div>

      <!-- MRR -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-md space-y-1 sm:col-span-2 lg:col-span-1">
        <span class="text-purple-600 dark:text-purple-400 text-xs font-bold">{{ $t('super.mrr') }}</span>
        <div class="text-2xl font-black text-purple-600 dark:text-purple-400 font-mono">
          {{ formatMoney(metrics.mrr || 0) }} <span class="text-xs text-slate-400">{{ $t('common.currency') }}</span>
        </div>
        <div class="text-[10px] text-slate-400 font-sans">Monthly Recurring Revenue</div>
      </div>
    </template>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

defineProps({
  metrics: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

const formatMoney = (val) => {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
