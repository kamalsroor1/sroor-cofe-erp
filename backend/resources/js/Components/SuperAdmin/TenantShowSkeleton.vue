<template>
  <div class="space-y-6" role="status" aria-busy="true" :aria-label="$t('super.loading_tenant_details')">
    <!-- Header: back button, avatar, name + status, action buttons -->
    <div
      class="bg-white dark:bg-slate-900/90 p-5 sm:p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6"
    >
      <div class="flex items-center gap-4">
        <Skeleton width="w-10" height="h-10" rounded="rounded-2xl" />
        <Skeleton width="w-12 sm:w-14" height="h-12 sm:h-14" rounded="rounded-2xl" />
        <div class="space-y-2">
          <Skeleton width="w-40 sm:w-56" height="h-6" rounded="rounded-lg" />
          <Skeleton width="w-52 sm:w-72" height="h-3.5" rounded="rounded-md" />
        </div>
      </div>
      <div class="flex flex-wrap gap-2.5">
        <Skeleton v-for="i in 4" :key="i" width="w-32" height="h-11" rounded="rounded-xl" />
      </div>
    </div>

    <!-- Stats grid (TenantStatsGrid) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <StatCardSkeleton v-for="i in 5" :key="i" />
    </div>

    <!-- Units + features columns -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-6"><CardSkeleton :rows="4" /></div>
      <div class="lg:col-span-6"><CardSkeleton :rows="4" /></div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import StatCardSkeleton from '../Common/Skeletons/StatCardSkeleton.vue';
import CardSkeleton from '../Common/Skeletons/CardSkeleton.vue';
</script>
