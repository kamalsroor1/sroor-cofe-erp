<script setup>
import BlendMockup from '../Mockups/BlendMockup.vue';
import MockScale from '../Mockups/MockScale.vue';
import PhoneReportMockup from '../Mockups/PhoneReportMockup.vue';
import PosMockup from '../Mockups/PosMockup.vue';
import { useMediaQuery } from '../../../Composables/useMediaQuery';

defineProps({
  /** blend = the coffee blend card with the daily-report phone over its end corner; pos = the POS window. */
  kind: { type: String, default: 'pos', validator: (value) => ['blend', 'pos'].includes(value) },
});

// Wide screens: the phone only overlaps the card's end edge (design 540×560). Narrow: a tighter 470 box so the
// scaled card stays readable, with the phone covering more of it.
const isWide = useMediaQuery('(min-width: 1024px)');
</script>

<template>
  <!-- Design px; MockScale shrinks the whole composition to the box. start/end mirror with the page direction. -->
  <MockScale v-if="kind === 'blend'" :key="isWide ? 'wide' : 'narrow'" :width="isWide ? 540 : 470" :height="560">
    <div class="absolute start-0 top-0 w-[380px]"><BlendMockup /></div>
    <div class="absolute end-0 top-[150px]"><PhoneReportMockup /></div>
  </MockScale>
  <MockScale v-else :width="700" :height="470"><PosMockup /></MockScale>
</template>
