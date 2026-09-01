<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true
    },
    variant: {
        type: String,
        default: 'default' // 'default' | 'success' | 'danger' | 'warning' | 'info' | 'primary' | 'purple' | 'slate'
    },
    dot: {
        type: Boolean,
        default: false
    },
    size: {
        type: String,
        default: 'sm' // 'xs' | 'sm' | 'md'
    }
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'success':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'danger':
            return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20';
        case 'warning':
            return 'bg-theme-light text-theme-primary text-theme-primary border-theme-border';
        case 'info':
            return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20';
        case 'primary':
            return 'bg-theme-light text-theme-primary border-theme-primary/25';
        case 'purple':
            return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20';
        case 'slate':
        default:
            return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700/60';
    }
});

const dotColor = computed(() => {
    switch (props.variant) {
        case 'success': return 'bg-emerald-500';
        case 'danger': return 'bg-rose-500';
        case 'warning': return 'bg-theme-primary';
        case 'info': return 'bg-blue-500';
        case 'primary': return 'bg-theme-primary';
        case 'purple': return 'bg-purple-500';
        default: return 'bg-slate-400';
    }
});

const sizeClass = computed(() => {
    switch (props.size) {
        case 'xs': return 'px-2 py-0.5 text-[10px] rounded-lg';
        case 'md': return 'px-3 py-1.5 text-xs rounded-xl';
        case 'sm':
        default:
            return 'px-2.5 py-1 text-[11px] rounded-xl';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 font-tajawal font-black border tracking-tight shrink-0 select-none"
        :class="[variantClasses, sizeClass]"
    >
        <span v-if="dot" class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotColor"></span>
        <span>{{ label }}</span>
    </span>
</template>
