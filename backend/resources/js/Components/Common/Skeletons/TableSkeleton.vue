<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    columnsCount: {
        type: Number,
        default: 6
    },
    rowsCount: {
        type: Number,
        default: 5
    }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 sm:p-5 shadow-xs space-y-4 font-tajawal">
        <!-- Desktop Table Skeleton View -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 pb-3">
                        <th v-for="c in columnsCount" :key="c" class="pb-3 px-3">
                            <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40">
                    <tr v-for="r in rowsCount" :key="r">
                        <td v-for="c in columnsCount" :key="c" class="py-3.5 px-3">
                            <Skeleton
                                :width="c === 1 ? 'w-28' : (c === columnsCount ? 'w-16' : 'w-20')"
                                height="h-3.5"
                                rounded="rounded-md"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards Skeleton View -->
        <div class="md:hidden space-y-3">
            <div
                v-for="r in rowsCount"
                :key="r"
                class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800/80 space-y-3 shadow-xs"
            >
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800/80 pb-2">
                    <Skeleton width="w-32" height="h-4" rounded="rounded-md" />
                    <Skeleton width="w-16" height="h-4" rounded="rounded-lg" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Skeleton width="w-full" height="h-3" rounded="rounded-md" />
                    <Skeleton width="w-full" height="h-3" rounded="rounded-md" />
                </div>
                <div class="flex items-center justify-between pt-1 border-t border-slate-200 dark:border-slate-800/80">
                    <Skeleton width="w-20" height="h-3" rounded="rounded-md" />
                    <Skeleton width="w-24" height="h-4" rounded="rounded-md" />
                </div>
            </div>
        </div>
    </div>
</template>
