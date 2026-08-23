<template>
  <div class="p-3.5 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
    <!-- Search Input -->
    <div class="relative flex-1 w-full">
      <Search class="w-4 h-4 absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
      <input
        :value="modelValue"
        type="text"
        :placeholder="$t('invoices.search_invoices_field_placeholder')"
        @input="$emit('update:modelValue', $event.target.value)"
        class="w-full pr-10 pl-4 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-theme-primary/30 min-h-[44px]"
      />
    </div>

    <!-- Date Quick Presets Pills (Horizontal scrollable with smooth touch) -->
    <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 scrollbar-none">
      <button
        type="button"
        v-for="preset in presets"
        :key="preset.id"
        @click="$emit('select-preset', preset.id)"
        class="min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer select-none active:scale-95 flex items-center justify-center"
        :class="activePreset === preset.id
          ? 'bg-theme-primary text-white shadow-xs font-black'
          : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
      >
        {{ preset.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { Search } from 'lucide-vue-next';

defineProps({
  modelValue: { type: String, default: '' },
  activePreset: { type: String, default: 'all' },
  presets: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['update:modelValue', 'select-preset']);
</script>
