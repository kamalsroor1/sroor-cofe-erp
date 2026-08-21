<script setup>
defineProps({
    width: {
        type: String,
        default: 'w-full'
    },
    height: {
        type: String,
        default: 'h-4'
    },
    rounded: {
        type: String,
        default: 'rounded-xl'
    },
    className: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <div
        class="bg-slate-200/80 dark:bg-slate-800/80 animate-pulse transition-all duration-300 relative overflow-hidden"
        :class="[width, height, rounded, className]"
    >
        <!-- Subtle Shimmer Sweep Effect -->
        <div class="absolute inset-0 -translate-x-full animate-[shimmer_2s_infinite] bg-gradient-to-r from-transparent via-white/20 dark:via-white/5 to-transparent"></div>
    </div>
</template>

<style scoped>
@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}
</style>
