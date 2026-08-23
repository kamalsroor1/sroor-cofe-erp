<template>
  <div class="space-y-6 font-tajawal">
    <!-- 1. KPI Cards Skeleton Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCardSkeleton v-for="n in 4" :key="n" />
    </div>

    <!-- 2. Analytics Row Skeleton (Bar Chart + Payment Dist) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Weekly Chart Skeleton -->
      <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between">
          <div class="space-y-1.5">
            <Skeleton width="w-40" height="h-4" rounded="rounded-md" />
            <Skeleton width="w-56" height="h-3" rounded="rounded-md" />
          </div>
          <Skeleton width="w-28" height="h-7" rounded="rounded-xl" />
        </div>
        <div class="h-48 flex items-end justify-between gap-3 pt-6 pb-2 px-2">
          <Skeleton v-for="n in 7" :key="n" width="w-full" :height="`h-${[24, 36, 28, 44, 32, 40, 48][n-1]}`" rounded="rounded-t-lg" />
        </div>
      </div>

      <!-- Payment Distribution Skeleton -->
      <div class="lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-4 shadow-xs flex flex-col justify-between">
        <div class="space-y-1.5">
          <Skeleton width="w-36" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-48" height="h-3" rounded="rounded-md" />
        </div>
        <div class="space-y-3 py-2">
          <div v-for="n in 4" :key="n" class="space-y-1.5">
            <div class="flex items-center justify-between">
              <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
              <Skeleton width="w-16" height="h-3" rounded="rounded-md" />
            </div>
            <Skeleton width="w-full" height="h-2" rounded="rounded-full" />
          </div>
        </div>
        <Skeleton width="w-full" height="h-10" rounded="rounded-2xl" />
      </div>
    </div>

    <!-- 3. Peak Hours Skeleton -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-4 shadow-xs">
      <div class="flex items-center justify-between">
        <div class="space-y-1.5">
          <Skeleton width="w-48" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-64" height="h-3" rounded="rounded-md" />
        </div>
        <Skeleton width="w-32" height="h-6" rounded="rounded-xl" />
      </div>
      <div class="h-28 flex items-end gap-1.5 justify-between pt-4">
        <Skeleton v-for="n in 24" :key="n" width="w-full" :height="`h-${Math.max(4, (n * 3) % 24)}`" rounded="rounded-md" />
      </div>
    </div>

    <!-- 4. Recent Invoices + Low Stock Bottom Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-3 shadow-xs">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
          <Skeleton width="w-36" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
        </div>
        <div class="space-y-2.5 pt-1">
          <div v-for="n in 5" :key="n" class="flex items-center justify-between py-2 border-b border-slate-50 dark:border-slate-800/40">
            <Skeleton width="w-24" height="h-3.5" rounded="rounded-md" />
            <Skeleton width="w-32" height="h-3.5" rounded="rounded-md" />
            <Skeleton width="w-20" height="h-3.5" rounded="rounded-md" />
            <Skeleton width="w-16" height="h-5" rounded="rounded-full" />
          </div>
        </div>
      </div>

      <div class="lg:col-span-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 space-y-3 shadow-xs">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
          <Skeleton width="w-32" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
        </div>
        <div class="space-y-2 pt-1">
          <div v-for="n in 4" :key="n" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
            <div class="space-y-1">
              <Skeleton width="w-28" height="h-3.5" rounded="rounded-md" />
              <Skeleton width="w-16" height="h-2.5" rounded="rounded-md" />
            </div>
            <Skeleton width="w-14" height="h-4" rounded="rounded-md" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
</script>
