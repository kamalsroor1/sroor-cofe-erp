<template>
  <div class="grid grid-cols-1 gap-3.5 font-tajawal">
    <div
      v-for="sec in sections"
      :key="sec.id"
      @click="$emit('select-section', sec.id)"
      class="p-4.5 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all active:scale-[0.98] cursor-pointer shadow-sm dark:shadow-lg flex items-center justify-between gap-4 group"
    >
      <div class="flex items-center gap-4 min-w-0">
        <div
          class="w-13 h-13 rounded-2xl flex items-center justify-center shrink-0 shadow-xs transition-transform group-hover:scale-105"
          :class="sec.iconBg"
        >
          <component :is="sec.icon" class="w-6 h-6" :class="sec.iconColor" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-black text-slate-900 dark:text-white group-hover:text-theme-primary dark:group-hover:text-theme-primary transition-colors truncate">
              {{ sec.label }}
            </h3>
            <span v-if="sec.badge" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-bold">
              {{ sec.badge }}
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
            {{ sec.description }}
          </p>
        </div>
      </div>

      <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-slate-400 group-hover:text-theme-primary group-hover:bg-slate-200 dark:group-hover:bg-slate-800 transition-all shrink-0">
        <ChevronLeft class="w-5 h-5" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ChevronLeft } from 'lucide-vue-next';

defineProps({
  sections: { type: Array, default: () => [] },
});

defineEmits(['select-section']);
</script>
