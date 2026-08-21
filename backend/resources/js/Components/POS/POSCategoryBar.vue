<script setup>
import { useNativeBridge } from '@/Composables/useNativeBridge';

defineProps({
    categories: {
        type: Array,
        default: () => []
    },
    selectedCategory: {
        type: String,
        default: 'all'
    },
    totalItemsCount: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['select-category']);
const { triggerHaptic } = useNativeBridge();

const onSelect = (category) => {
    triggerHaptic('light');
    emit('select-category', category);
};
</script>

<template>
    <div
        role="tablist"
        aria-label="Categories Carousel"
        class="flex items-center gap-2 overflow-x-auto py-1 px-0.5 text-xs font-tajawal select-none scrollbar-none no-scrollbar touch-pan-x"
    >
        <!-- 'All Categories' Tab Button -->
        <button
            type="button"
            role="tab"
            :aria-selected="selectedCategory === 'all'"
            class="h-10 px-4 rounded-2xl font-black transition-all duration-200 shrink-0 cursor-pointer flex items-center gap-1.5 active:scale-95 shadow-xs"
            :class="selectedCategory === 'all'
                ? 'btn-primary-theme shadow-theme-primary ring-2 ring-white/20 scale-102'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 hover:text-slate-900 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white border border-slate-200/80 dark:border-slate-800'"
            @click="onSelect('all')"
        >
            <span>☕</span>
            <span>{{ $t('common.all') }}</span>
            <span
                class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                :class="selectedCategory === 'all' ? 'bg-black/20 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
            >
                {{ totalItemsCount }}
            </span>
        </button>

        <!-- Dynamic Category Tabs -->
        <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            role="tab"
            :aria-selected="selectedCategory === cat"
            class="h-10 px-4 rounded-2xl font-black transition-all duration-200 shrink-0 cursor-pointer flex items-center gap-1.5 active:scale-95 shadow-xs"
            :class="selectedCategory === cat
                ? 'btn-primary-theme shadow-theme-primary ring-2 ring-white/20 scale-102'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 hover:text-slate-900 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white border border-slate-200/80 dark:border-slate-800'"
            @click="onSelect(cat)"
        >
            <span>🏷️</span>
            <span>{{ cat }}</span>
        </button>
    </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar,
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.scrollbar-none,
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
