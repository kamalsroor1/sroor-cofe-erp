<template>
  <div class="space-y-6" aria-busy="true">
    <!-- Header Skeleton -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-4 space-y-2">
      <Skeleton width="w-48 max-w-full" height="h-5" rounded="rounded-md" />
      <Skeleton width="w-72 max-w-full" height="h-3.5" rounded="rounded-sm" />
    </div>

    <!-- Active Unit Pills Skeleton -->
    <div class="flex flex-wrap gap-2.5">
      <Skeleton
        v-for="(pillWidth, idx) in pillWidths"
        :key="idx"
        :width="pillWidth"
        height="h-11"
        rounded="rounded-xl"
      />
    </div>

    <!-- Add Custom Unit Section Skeleton -->
    <div
      class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3"
    >
      <Skeleton width="w-44 max-w-full" height="h-3.5" rounded="rounded-sm" />
      <div class="flex items-center gap-2">
        <Skeleton width="flex-1" height="h-11" rounded="rounded-xl" />
        <Skeleton width="w-28" height="h-11" rounded="rounded-xl" />
      </div>
    </div>

    <!-- Suggested Presets Skeleton -->
    <div class="space-y-2">
      <Skeleton width="w-56 max-w-full" height="h-3.5" rounded="rounded-sm" />
      <div class="flex flex-wrap gap-2">
        <Skeleton v-for="i in 6" :key="i" width="w-20" height="h-8" rounded="rounded-xl" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';

const pillWidths = ['w-20', 'w-24', 'w-16', 'w-28', 'w-20', 'w-24', 'w-20', 'w-24'];
</script>
