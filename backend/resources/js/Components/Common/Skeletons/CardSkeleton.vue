<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    rows: {
        type: Number,
        default: 4
    },
    hasHeader: {
        type: Boolean,
        default: true
    },
    hasChart: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-6 space-y-4 shadow-xs font-tajawal">
        <!-- Header Skeleton -->
        <div v-if="hasHeader" class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <Skeleton width="w-5" height="h-5" rounded="rounded-md" />
                <Skeleton width="w-36" height="h-4" rounded="rounded-md" />
            </div>
            <Skeleton width="w-16" height="h-3" rounded="rounded-md" />
        </div>

        <!-- Optional Chart Placeholder -->
        <div v-if="hasChart" class="h-44 sm:h-52 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800/80 p-4 flex items-end gap-3 justify-between">
            <Skeleton v-for="i in 7" :key="i" width="w-full" :height="`h-${(i % 4 + 2) * 8}`" rounded="rounded-t-lg" />
        </div>

        <!-- List / Rows Skeleton -->
        <div v-else class="space-y-3">
            <div v-for="i in rows" :key="i" class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/60 flex items-center justify-between gap-4">
                <div class="space-y-1.5 flex-1">
                    <Skeleton width="w-2/3" height="h-3.5" rounded="rounded-md" />
                    <Skeleton width="w-1/3" height="h-2.5" rounded="rounded-sm" />
                </div>
                <Skeleton width="w-20" height="h-5" rounded="rounded-xl" />
            </div>
        </div>
    </div>
</template>
