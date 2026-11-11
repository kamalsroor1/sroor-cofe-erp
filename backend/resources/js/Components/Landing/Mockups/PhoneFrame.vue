<script setup>
defineProps({
  width: { type: Number, default: 200 },
  height: { type: Number, default: 410 },
});
</script>

<template>
  <div class="rounded-[34px] bg-[#0B0F0E] p-2 mk-shadow-float" :style="{ width: `${width}px`, height: `${height}px` }">
    <div class="size-full overflow-hidden rounded-[26px] bg-mk-app-bg">
      <slot />
    </div>
  </div>
</template>
