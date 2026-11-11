<script setup>
import MockScale from './MockScale.vue';

defineProps({
  /** Design size of the stage; slotted children are absolutely positioned in these px. */
  width: { type: Number, default: 640 },
  height: { type: Number, default: 470 },
});
</script>

<template>
  <!-- Tinted rounded panel framing a mockup composition; the whole composition scales with its box. -->
  <MockScale :width="width" :height="height">
    <div class="relative size-full overflow-hidden rounded-[32px] bg-mk-soft">
      <slot />
    </div>
  </MockScale>
</template>
