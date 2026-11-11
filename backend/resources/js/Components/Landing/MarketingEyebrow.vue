<script setup>
defineProps({
  icon: { type: [Object, Function], default: null },
  /** Translucent variant for the dark hero / band backgrounds. */
  onDark: { type: Boolean, default: false },
});
</script>

<template>
  <p
    class="inline-flex min-h-9 w-fit items-center gap-2 rounded-full px-3.5 text-sm font-medium"
    :class="onDark ? 'border border-white/15 bg-white/8 text-mk-hero-accent' : 'bg-mk-soft text-mk-soft-ink'"
  >
    <component :is="icon" v-if="icon" class="size-4 shrink-0" aria-hidden="true" />
    <span><slot /></span>
  </p>
</template>
