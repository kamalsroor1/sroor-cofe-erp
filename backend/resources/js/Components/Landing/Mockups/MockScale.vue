<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  /** Design size of the slotted mockup, in px. It is drawn at this size and scaled to the box width. */
  width: { type: Number, required: true },
  height: { type: Number, required: true },
  /** Never upscale past this factor. */
  maxScale: { type: Number, default: 1 },
});

const root = ref(null);
const scale = ref(0);
let observer = null;

const measure = () => {
  const available = root.value?.clientWidth || 0;
  scale.value = Math.min(props.maxScale, available / props.width);
};

onMounted(() => {
  measure();
  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(measure);
    observer.observe(root.value);
  } else {
    window.addEventListener('resize', measure);
  }
});
onBeforeUnmount(() => {
  observer?.disconnect();
  window.removeEventListener('resize', measure);
});
</script>

<template>
  <!-- Decorative: hidden from assistive tech. aspect-ratio reserves the height before the first measure. -->
  <div
    ref="root"
    class="relative w-full select-none"
    aria-hidden="true"
    :style="{ aspectRatio: `${width} / ${height}`, maxWidth: `${width * maxScale}px` }"
  >
    <!-- Physical left/top-left on purpose: the scaled box always spans the container, in RTL and LTR. -->
    <div
      class="pointer-events-none absolute top-0 left-0 origin-top-left"
      :style="{
        width: `${width}px`,
        height: `${height}px`,
        transform: `scale(${scale})`,
        visibility: scale ? 'visible' : 'hidden',
      }"
    >
      <slot />
    </div>
  </div>
</template>
