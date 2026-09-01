<template>
  <button
    type="button"
    @click="$emit('click', $event)"
    class="min-h-[44px] px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer border select-none font-tajawal active:scale-[0.98]"
    :class="isOpen || count > 0
      ? 'bg-theme-primary/10 border-theme-primary text-theme-primary font-black shadow-xs'
      : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
  >
    <component :is="icon || SlidersHorizontal" class="w-4 h-4 text-theme-primary shrink-0" />
    <span>{{ label || $t('invoices.filter_search') }}</span>
    <span
      v-if="count > 0"
      class="w-5 h-5 rounded-full bg-theme-primary text-white text-[10px] font-black flex items-center justify-center shrink-0"
    >
      {{ count }}
    </span>
  </button>
</template>

<script setup>
import { SlidersHorizontal } from 'lucide-vue-next';

defineProps({
  isOpen: { type: Boolean, default: false },
  count: { type: Number, default: 0 },
  label: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
});

defineEmits(['click']);
</script>
