<template>
  <svg
    v-if="encoded"
    :viewBox="`0 0 ${encoded.width} ${BAR_HEIGHT}`"
    preserveAspectRatio="none"
    shape-rendering="crispEdges"
    class="block w-full h-full"
    role="img"
    :aria-label="code"
    :data-symbology="encoded.symbology"
    data-testid="label-barcode"
  >
    <rect :width="encoded.width" :height="BAR_HEIGHT" fill="#ffffff" />
    <rect
      v-for="(bar, idx) in encoded.bars"
      :key="idx"
      :x="bar.x"
      y="0"
      :width="bar.w"
      :height="BAR_HEIGHT"
      fill="#000000"
    />
  </svg>
</template>

<script setup>
import { computed } from 'vue';
import { encodeBarcode } from '../../../helpers/barcode';

// Bars are drawn in module units and stretched to the box: the label decides the printed size.
const BAR_HEIGHT = 40;

const props = defineProps({
  code: { type: String, default: '' },
  symbology: { type: String, default: 'auto' },
});

const encoded = computed(() => encodeBarcode(props.code, props.symbology));
</script>
