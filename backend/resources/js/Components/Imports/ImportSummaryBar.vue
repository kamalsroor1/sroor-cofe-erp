<script setup>
import { computed } from 'vue';

const props = defineProps({
  summary: { type: Object, required: true },
  filter: { type: String, required: true },
});

defineEmits(['update:filter']);

const chips = computed(() => [
  {
    key: 'all',
    label: 'import.ui.summary.total',
    count: props.summary.total,
    tone: 'text-slate-700 dark:text-slate-200',
  },
  {
    key: 'valid',
    label: 'import.ui.summary.valid',
    count: props.summary.valid,
    tone: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    key: 'invalid',
    label: 'import.ui.summary.invalid',
    count: props.summary.invalid,
    tone: 'text-rose-600 dark:text-rose-400',
  },
  {
    key: 'duplicate',
    label: 'import.ui.summary.duplicates',
    count: props.summary.duplicates,
    tone: 'text-amber-600 dark:text-amber-400',
  },
]);
</script>

<template>
  <div
    class="grid grid-cols-2 lg:grid-cols-4 gap-3 font-tajawal"
    role="group"
    :aria-label="$t('import.ui.filter.label')"
  >
    <button
      v-for="chip in chips"
      :key="chip.key"
      type="button"
      class="min-h-[64px] px-4 py-3 rounded-2xl border-2 bg-white dark:bg-slate-900 text-start transition active:scale-[0.99] cursor-pointer"
      :class="filter === chip.key ? '' : 'border-slate-200 dark:border-slate-800'"
      :style="filter === chip.key ? { borderColor: 'var(--color-primary)' } : null"
      :aria-pressed="filter === chip.key"
      @click="$emit('update:filter', chip.key)"
    >
      <span class="block text-xl font-black tabular-nums" :class="chip.tone">{{ chip.count }}</span>
      <span class="block text-xs font-bold text-slate-500 dark:text-slate-400">{{ $t(chip.label) }}</span>
    </button>
  </div>
</template>
