<script setup>
defineProps({
  tone: {
    type: String,
    default: 'muted',
    validator: (value) => ['primary', 'soft', 'surface', 'muted', 'outline', 'warn'].includes(value),
  },
  /** Chip height in px (design: 20 / 22 / 26 / 28). */
  height: { type: Number, default: 26 },
});

const TONES = {
  primary: 'bg-mk-primary text-mk-primary-ink',
  soft: 'bg-mk-soft text-mk-soft-ink',
  surface: 'bg-mk-app-surface text-mk-app-ink',
  muted: 'bg-mk-app-bg text-mk-app-muted',
  outline: 'border border-mk-app-line bg-mk-app-surface text-mk-app-ink',
  warn: 'bg-mk-accent-soft text-mk-warn-ink',
};
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 font-medium whitespace-nowrap"
    :class="[TONES[tone], height <= 22 ? 'text-[10px]' : 'text-[11px]']"
    :style="{ height: `${height}px` }"
  >
    <slot />
  </span>
</template>
