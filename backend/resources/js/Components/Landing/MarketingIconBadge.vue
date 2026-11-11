<script setup>
import { computed } from 'vue';

const props = defineProps({
  icon: { type: [Object, Function], required: true },
  /** Tile edge in px; the icon is half of it. */
  size: { type: Number, default: 44 },
});

const box = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  borderRadius: `${Math.round(props.size * 0.28)}px`,
}));
const glyph = computed(() => ({ width: `${props.size / 2}px`, height: `${props.size / 2}px` }));
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center bg-mk-soft text-mk-primary"
    :style="box"
    aria-hidden="true"
  >
    <component :is="icon" :style="glyph" />
  </span>
</template>
