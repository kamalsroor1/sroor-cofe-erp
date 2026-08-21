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
    <!-- Multiple Table Rows Skeleton -->
    <tr v-for="r in rowsCount" :key="r" class="border-b border-slate-100 dark:border-slate-800/40">
        <td v-for="c in columnsCount" :key="c" class="py-3.5 px-3">
            <Skeleton
                :width="c === 1 ? 'w-24' : (c === columnsCount ? 'w-16' : 'w-full')"
                height="h-3.5"
                rounded="rounded-md"
            />
        </td>
    </tr>
</template>
