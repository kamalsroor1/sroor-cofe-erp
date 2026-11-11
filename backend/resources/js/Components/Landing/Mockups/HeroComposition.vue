<script setup>
import MockScale from './MockScale.vue';
import PhoneReportMockup from './PhoneReportMockup.vue';
import PosMockup from './PosMockup.vue';
</script>

<template>
  <!-- Hero visual: the POS window with the daily-report phone overlapping its end corner (design 630×560). -->
  <MockScale :width="630" :height="560">
    <div class="absolute start-0 top-10 w-[630px]">
      <MockScale :width="700" :height="470"><PosMockup /></MockScale>
    </div>
    <div class="absolute -end-12 top-[170px]"><PhoneReportMockup /></div>
  </MockScale>
</template>
