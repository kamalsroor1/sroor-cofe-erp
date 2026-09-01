<script setup>
import { computed } from 'vue';
import DynamicIcon from './DynamicIcon.vue';

const props = defineProps({
    // --- Core Props (Original) ---
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    currency: { type: String, default: '' },
    variant: {
        type: String,
        default: 'default', // 'default' | 'primary' | 'success' | 'danger' | 'warning' | 'cyan' | 'indigo' | 'slate'
    },
    icon: { type: [Object, Function, String], default: null },
    subtitle: { type: String, default: '' },

    // --- Extended Props (added for Dashboard KPI cards) ---
    // Custom icon background and text color classes (e.g. 'bg-emerald-500/10', 'text-emerald-500')
    iconBg: { type: String, default: 'bg-slate-100 dark:bg-slate-800' },
    iconColor: { type: String, default: 'text-slate-500' },
    // Footer row: two items aligned left & right
    footerLeft: { type: String, default: '' },
    footerRight: { type: String, default: '' },
    footerRightClass: { type: String, default: 'text-slate-600 dark:text-slate-300' },
});

const valueColorClass = computed(() => {
    switch (props.variant) {
        case 'primary':  return 'text-theme-primary';
        case 'success':  return 'text-emerald-600 dark:text-emerald-400';
        case 'danger':   return 'text-rose-600 dark:text-rose-400';
        case 'warning':  return 'text-theme-primary';
        case 'cyan':     return 'text-cyan-600 dark:text-cyan-400';
        case 'indigo':   return 'text-indigo-600 dark:text-indigo-400';
        case 'slate':    return 'text-slate-700 dark:text-slate-300';
        default:         return 'text-slate-900 dark:text-white';
    }
});
</script>

<template>
    <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-xl space-y-2 relative overflow-hidden group font-tajawal transition hover:border-slate-300 dark:hover:border-slate-700">
        <!-- Header: Title + Icon -->
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 truncate">{{ title }}</span>
            <div
                v-if="icon"
                class="w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0"
                :class="[iconBg, iconColor]"
            >
                <DynamicIcon :name="icon" class="w-4 h-4 shrink-0" />
            </div>
        </div>

        <!-- Main Value -->
        <div class="text-xl sm:text-2xl font-black font-mono tracking-tight" :class="valueColorClass">
            {{ value }}
            <span v-if="currency" class="text-xs font-sans text-slate-400 font-bold">{{ currency }}</span>
        </div>

        <!-- Footer Row (left + right) -->
        <div v-if="footerLeft || footerRight" class="text-[11px] text-slate-500 dark:text-slate-400 font-bold flex items-center justify-between">
            <span>{{ footerLeft }}</span>
            <span v-if="footerRight" class="font-mono font-black" :class="footerRightClass">{{ footerRight }}</span>
        </div>

        <!-- Legacy subtitle (for backward compatibility with other pages) -->
        <p v-else-if="subtitle" class="text-[10.5px] text-slate-400 font-medium truncate">{{ subtitle }}</p>
    </div>
</template>
