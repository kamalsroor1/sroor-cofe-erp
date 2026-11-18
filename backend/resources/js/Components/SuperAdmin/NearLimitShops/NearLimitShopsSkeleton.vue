<script setup>
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
</script>

<template>
  <div class="space-y-3" aria-busy="true">
    <div
      v-for="n in 4"
      :key="n"
      class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center p-4 rounded-2xl border border-slate-200 dark:border-slate-800"
    >
      <div class="md:col-span-4 space-y-2">
        <Skeleton width="w-2/3" height="h-4" />
        <Skeleton width="w-1/3" height="h-3" />
      </div>
      <Skeleton class-name="md:col-span-2" width="w-24" height="h-4" />
      <div class="md:col-span-6 space-y-2">
        <Skeleton width="w-full" height="h-2.5" rounded="rounded-full" />
        <Skeleton width="w-1/4" height="h-3" />
      </div>
    </div>
  </div>
</template>
