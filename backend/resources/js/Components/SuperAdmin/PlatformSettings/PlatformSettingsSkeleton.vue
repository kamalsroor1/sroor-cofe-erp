<script setup>
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
</script>

<template>
  <div class="space-y-6" role="status" aria-busy="true" :aria-label="$t('common.loading')">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div
        v-for="card in 2"
        :key="card"
        class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 space-y-5"
      >
        <div class="flex items-center gap-2 pb-3 border-b border-slate-200 dark:border-slate-800">
          <Skeleton width="w-7" height="h-7" />
          <div class="space-y-1.5">
            <Skeleton width="w-36" height="h-3.5" rounded="rounded-md" />
            <Skeleton width="w-56" height="h-2.5" rounded="rounded-md" />
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="field in 4" :key="field" class="space-y-2">
            <Skeleton width="w-24" height="h-3" rounded="rounded-md" />
            <Skeleton height="h-11" />
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-6 space-y-5"
    >
      <Skeleton width="w-40" height="h-4" rounded="rounded-md" />
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Skeleton height="h-11" />
        <Skeleton height="h-11" />
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div
        v-for="tile in 4"
        :key="tile"
        class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 space-y-3"
      >
        <Skeleton width="w-32" height="h-3.5" rounded="rounded-md" />
        <Skeleton height="h-28" rounded="rounded-2xl" />
        <Skeleton height="h-11" />
      </div>
    </div>
  </div>
</template>
