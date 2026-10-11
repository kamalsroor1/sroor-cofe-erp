<script setup>
import Skeleton from './Skeleton.vue';
</script>

<template>
  <div
    class="metric-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-3 sm:p-5 shadow-xs space-y-2 sm:space-y-3 font-tajawal select-none"
    data-metric-card="true"
  >
    <!-- Title & Icon Row -->
    <div class="flex items-center justify-between gap-2">
      <Skeleton width="w-20 sm:w-24" height="h-3 sm:h-3.5" rounded="rounded-md" />
      <Skeleton width="w-6 sm:w-7" height="h-6 sm:h-7" rounded="rounded-lg" />
    </div>

    <!-- Big Numeric Value -->
    <div class="space-y-1 py-0.5">
      <Skeleton width="w-24 sm:w-32" height="h-6 sm:h-8" rounded="rounded-lg" />
    </div>

    <!-- Subtitle -->
    <div class="pt-0.5">
      <Skeleton width="w-20 sm:w-28" height="h-2.5 sm:h-3" rounded="rounded-md" />
    </div>
  </div>
</template>
