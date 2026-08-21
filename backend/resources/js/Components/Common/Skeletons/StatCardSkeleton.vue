<script setup>
import Skeleton from './Skeleton.vue';
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-3 font-tajawal">
        <!-- Title & Icon Row -->
        <div class="flex items-center justify-between gap-2">
            <Skeleton width="w-24" height="h-3.5" rounded="rounded-md" />
            <Skeleton width="w-6" height="h-6" rounded="rounded-lg" />
        </div>

        <!-- Big Numeric Value -->
        <div class="space-y-1 py-0.5">
            <Skeleton width="w-32" height="h-7 sm:h-8" rounded="rounded-lg" />
        </div>

        <!-- Subtitle -->
        <div class="pt-0.5">
            <Skeleton width="w-28" height="h-3" rounded="rounded-md" />
        </div>
    </div>
</template>
