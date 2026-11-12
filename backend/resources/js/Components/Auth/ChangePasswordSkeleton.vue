<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
</script>

<template>
  <div class="space-y-4" role="status" aria-busy="true" data-testid="change-password-skeleton">
    <div v-for="n in 3" :key="n" class="space-y-2">
      <Skeleton width="w-28" height="h-3" />
      <Skeleton height="h-11" />
    </div>
    <Skeleton height="h-12" rounded="rounded-2xl" />
  </div>
</template>
