<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
  // Mirrors MetricCard `dense` (strip layout).
  dense: { type: Boolean, default: false },
});
</script>

<template>
  <div
    v-if="dense"
    class="metric-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-2.5 shadow-xs flex items-center gap-2.5 font-tajawal select-none"
    data-metric-card="true"
  >
    <Skeleton width="w-8" height="h-8" rounded="rounded-lg" />
    <div class="flex-1 space-y-1.5">
      <Skeleton width="w-16" height="h-2.5" rounded="rounded-md" />
      <Skeleton width="w-24" height="h-4" rounded="rounded-md" />
    </div>
  </div>
  <div
    v-else
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
