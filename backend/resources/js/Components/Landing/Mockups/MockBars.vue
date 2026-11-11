<script setup>
import { computed } from 'vue';

const props = defineProps({
  values: { type: Array, required: true },
  /** Index drawn solid while the others fade; -1 = all solid. */
  highlight: { type: Number, default: -1 },
  height: { type: Number, default: 110 },
  gap: { type: Number, default: 8 },
});

const peak = computed(() => Math.max(...props.values, 1));
</script>

<template>
  <!-- Charts read chronologically left to right in both languages. -->
  <div class="flex w-full items-end" dir="ltr" :style="{ height: `${height}px`, gap: `${gap}px` }">
    <span
      v-for="(value, index) in values"
      :key="index"
      class="flex-1 rounded-t bg-mk-primary"
      :class="{ 'opacity-35': highlight >= 0 && index !== highlight }"
      :style="{ height: `${Math.max(4, Math.round((height * value) / peak))}px` }"
    ></span>
  </div>
</template>
