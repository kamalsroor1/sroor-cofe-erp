<script setup>
import { computed } from 'vue';
import BrandMark from '../Brand/BrandMark.vue';

const props = defineProps({
  /** Platform name from GET /public/plans (brand.name); the translated product name until it loads. */
  name: { type: String, default: '' },
  size: { type: Number, default: 36 },
});

const wordmark = computed(() => ({ fontSize: `${Math.round(props.size * 0.72)}px` }));
</script>

<template>
  <span class="inline-flex min-w-0 items-center gap-2.5">
    <BrandMark :size="size" decorative />
    <span class="font-brand truncate leading-none font-bold text-mk-ink" :style="wordmark">{{
      name || $t('brand.name')
    }}</span>
  </span>
</template>
