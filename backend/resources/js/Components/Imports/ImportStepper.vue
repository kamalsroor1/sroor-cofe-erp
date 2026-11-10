<script setup>
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';

const props = defineProps({
  current: { type: String, required: true },
});

const steps = ['type', 'upload', 'preview', 'result'];
const labelKeys = {
  type: 'import.ui.steps.type',
  upload: 'import.ui.steps.upload',
  preview: 'import.ui.steps.preview',
  result: 'import.ui.steps.done',
};
const currentIndex = computed(() => steps.indexOf(props.current));
</script>

<template>
  <ol class="flex items-center gap-2 font-tajawal" aria-label="steps">
    <li
      v-for="(key, index) in steps"
      :key="key"
      class="flex items-center gap-2 min-w-0"
      :class="{ 'flex-1': index < steps.length - 1 }"
    >
      <span
        class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-xs font-black border transition"
        :class="
          index <= currentIndex
            ? 'text-slate-950 border-transparent'
            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700'
        "
        :style="index <= currentIndex ? { backgroundColor: 'var(--color-primary)' } : null"
      >
        <Check v-if="index < currentIndex" class="w-4 h-4" />
        <span v-else>{{ index + 1 }}</span>
      </span>
      <span
        class="hidden sm:block text-xs font-black truncate"
        :class="index === currentIndex ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'"
      >
        {{ $t(labelKeys[key]) }}
      </span>
      <span v-if="index < steps.length - 1" class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></span>
    </li>
  </ol>
</template>
