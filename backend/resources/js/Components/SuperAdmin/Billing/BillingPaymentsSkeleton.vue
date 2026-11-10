<script setup>
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
</script>

<template>
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4" role="status" aria-busy="true" :aria-label="$t('common.loading')">
    <div
      v-for="card in 4"
      :key="card"
      class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 sm:p-5 space-y-4"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-2">
          <Skeleton width="w-40" height="h-4" rounded="rounded-md" />
          <Skeleton width="w-28" height="h-3" rounded="rounded-md" />
        </div>
        <Skeleton width="w-24" height="h-6" rounded="rounded-full" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="cell in 4" :key="cell" class="space-y-1.5">
          <Skeleton width="w-16" height="h-2.5" rounded="rounded-md" />
          <Skeleton width="w-24" height="h-3.5" rounded="rounded-md" />
        </div>
      </div>
      <div class="flex gap-2">
        <Skeleton width="w-28" height="h-11" />
        <Skeleton width="w-24" height="h-11" />
      </div>
    </div>
  </div>
</template>
