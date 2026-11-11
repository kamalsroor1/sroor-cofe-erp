<script setup>
import InventoryMockup from './InventoryMockup.vue';
import MockScale from './MockScale.vue';
import MockStage from './MockStage.vue';
import PosMockup from './PosMockup.vue';
import ReportsMockup from './ReportsMockup.vue';
import TransferToast from './TransferToast.vue';

defineProps({
  /** Which app screen the tinted stage frames (build_landing.py features()). */
  kind: { type: String, required: true, validator: (value) => ['pos', 'inventory', 'reports'].includes(value) },
});

/*
 * Positions are the design's 640×470 stage in px. `end-*` (inset-inline-end) mirrors the design's left offsets,
 * so the composition flips with the page direction. Each window is drawn at its design size and scaled
 * by MockScale to the wrapper width (the design's scale factor).
 */
</script>

<template>
  <MockStage :width="640" :height="470">
    <template v-if="kind === 'pos'">
      <div class="absolute end-[30px] top-10 w-[602px]">
        <MockScale :width="700" :height="470"><PosMockup /></MockScale>
      </div>
    </template>
    <template v-else-if="kind === 'inventory'">
      <div class="absolute end-[50px] top-[50px] w-[558px]">
        <MockScale :width="620" :height="400"><InventoryMockup /></MockScale>
      </div>
      <div class="absolute end-6 top-[310px]"><TransferToast /></div>
    </template>
    <template v-else>
      <div class="absolute end-[47px] top-9 w-[546px]">
        <MockScale :width="620" :height="470"><ReportsMockup /></MockScale>
      </div>
    </template>
  </MockStage>
</template>
