<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    currency: {
        type: String,
        default: ''
    },
    variant: {
        type: String,
        default: 'default' // 'default' | 'primary' | 'success' | 'danger' | 'warning'
    },
    icon: {
        type: [Object, Function, String],
        default: null
    },
    subtitle: {
        type: String,
        default: ''
    }
});

const valueColorClass = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'text-theme-primary';
        case 'success':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'danger':
            return 'text-rose-600 dark:text-rose-400';
        case 'warning':
            return 'text-theme-primary text-theme-primary';
        case 'slate':
            return 'text-slate-700 dark:text-slate-300';
        default:
            return 'text-slate-900 dark:text-white';
    }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3.5 sm:p-5 shadow-xs space-y-1.5 font-tajawal transition hover:border-slate-300 dark:hover:border-slate-700">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-bold truncate">
                {{ title }}
            </span>
            <div v-if="icon" class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center text-xs shrink-0">
                <component v-if="typeof icon === 'object' || typeof icon === 'function'" :is="icon" class="w-3.5 h-3.5" />
                <span v-else>{{ icon }}</span>
            </div>
        </div>

        <div class="text-lg sm:text-2xl font-black font-mono tracking-tight" :class="valueColorClass">
            {{ value }} <span v-if="currency" class="text-[11px] font-tajawal font-bold text-slate-500 dark:text-slate-400">{{ currency }}</span>
        </div>

        <p v-if="subtitle" class="text-[10.5px] text-slate-400 font-medium truncate">
            {{ subtitle }}
        </p>
    </div>
</template>
