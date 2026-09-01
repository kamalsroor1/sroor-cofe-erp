<template>
  <div class="space-y-2 font-tajawal">
    <div class="text-xs text-slate-500 dark:text-slate-400 font-bold">
      {{ $t('super.suggested_presets_title') }}
    </div>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="preset in presets"
        :key="preset"
        type="button"
        @click="$emit('add-preset', preset)"
        :disabled="units.includes(preset)"
        class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed active:scale-95"
        :class="units.includes(preset) ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-400' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-purple-500'"
      >
        + {{ preset }}
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  presets: { type: Array, default: () => [] },
  units: { type: Array, default: () => [] },
});

defineEmits(['add-preset']);
</script>
