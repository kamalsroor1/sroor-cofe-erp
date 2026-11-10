<script setup>
import Skeleton from '@/Components/Common/Skeletons/Skeleton.vue';
import CardSkeleton from '@/Components/Common/Skeletons/CardSkeleton.vue';
</script>

<template>
  <div class="space-y-4" aria-busy="true">
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4"
    >
      <Skeleton width="w-40" height="h-5" />
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Skeleton v-for="n in 4" :key="n" height="h-14" rounded="rounded-2xl" />
      </div>
      <div class="flex gap-2">
        <Skeleton width="w-40" height="h-11" rounded="rounded-xl" />
        <Skeleton width="w-32" height="h-11" rounded="rounded-xl" />
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <CardSkeleton :rows="6" />
      <CardSkeleton :rows="3" />
    </div>
    <CardSkeleton :rows="4" />
  </div>
</template>
