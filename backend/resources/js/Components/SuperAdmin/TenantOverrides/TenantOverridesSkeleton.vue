<script setup>
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
</script>

<template>
  <div class="space-y-3" role="status" aria-busy="true" :aria-label="$t('common.loading')">
    <div
      v-for="row in 5"
      :key="row"
      class="grid grid-cols-2 md:grid-cols-12 gap-3 items-center rounded-2xl border border-slate-200 dark:border-slate-800 p-4"
    >
      <Skeleton width="w-32" height="h-4" rounded="rounded-md" class-name="col-span-2 md:col-span-2" />
      <Skeleton width="w-16" height="h-4" rounded="rounded-md" class-name="md:col-span-2" />
      <Skeleton width="w-full" height="h-8" class-name="col-span-2 md:col-span-4" />
      <Skeleton width="w-16" height="h-4" rounded="rounded-md" class-name="md:col-span-2" />
      <Skeleton width="w-24" height="h-11" class-name="col-span-2 md:col-span-2" />
    </div>
  </div>
</template>
