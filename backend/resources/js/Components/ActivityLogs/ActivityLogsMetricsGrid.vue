<template>
  <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <StatCardSkeleton v-for="i in 4" :key="i" />
  </div>

  <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4 font-tajawal">
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-1">
      <div class="text-slate-500 dark:text-slate-400 text-xs font-bold">{{ $t('activity.today_total') }}</div>
      <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ stats.today_total || 0 }}</div>
      <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('activity.sub_total_desc') }}</div>
    </div>

    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-1">
      <div class="text-rose-500 dark:text-rose-400 text-xs font-bold">{{ $t('activity.today_critical') }}</div>
      <div class="text-2xl font-black text-rose-500 dark:text-rose-400 font-mono">{{ stats.today_critical || 0 }}</div>
      <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('activity.sub_critical_desc') }}</div>
    </div>

    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-1">
      <div class="text-theme-primary text-xs font-bold">{{ $t('activity.today_users') }}</div>
      <div class="text-2xl font-black text-theme-primary font-mono">{{ stats.today_users || 0 }}</div>
      <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('activity.sub_users_desc') }}</div>
    </div>

    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg space-y-1">
      <div class="text-cyan-500 dark:text-cyan-400 text-xs font-bold">{{ $t('activity.today_stores') }}</div>
      <div class="text-2xl font-black text-cyan-500 dark:text-cyan-400 font-mono">{{ stats.today_stores || 0 }}</div>
      <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $t('activity.sub_stores_desc') }}</div>
    </div>
  </div>
</template>

<script setup>
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';

defineProps({
  stats: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});
</script>
