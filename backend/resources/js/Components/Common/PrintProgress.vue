<template>
  <div
    role="status"
    class="p-4 rounded-2xl bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 shadow-md flex flex-col sm:flex-row sm:items-center gap-3 no-print print:hidden font-tajawal"
  >
    <div class="flex-1 min-w-0 space-y-2">
      <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ label }}</p>
      <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
        <div class="h-full rounded-full bg-theme-primary transition-all" :style="{ width: `${percent}%` }"></div>
      </div>
    </div>
    <BaseButton type="button" variant="secondary" size="md" class="font-bold" @click="$emit('cancel')">
      {{ $t('common.cancel') }}
    </BaseButton>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import BaseButton from './BaseButton.vue';

// "Fetching every page before printing" bar with a cancel button (customer statement, reports).
const props = defineProps({
  label: { type: String, required: true },
  loaded: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
});

defineEmits(['cancel']);

const percent = computed(() => (props.total > 0 ? Math.min(100, Math.round((props.loaded / props.total) * 100)) : 0));
</script>
