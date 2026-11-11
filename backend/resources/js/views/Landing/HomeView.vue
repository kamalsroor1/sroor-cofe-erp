<script setup>
import { onMounted, ref } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import LandingFaq from '../../Components/Landing/LandingFaq.vue';
import LandingFinalCta from '../../Components/Landing/LandingFinalCta.vue';
import HomeAudience from '../../Components/Landing/Home/HomeAudience.vue';
import HomeCoffeeBand from '../../Components/Landing/Home/HomeCoffeeBand.vue';
import HomeDevicesSteps from '../../Components/Landing/Home/HomeDevicesSteps.vue';
import HomeHero from '../../Components/Landing/Home/HomeHero.vue';
import HomeMoreTiles from '../../Components/Landing/Home/HomeMoreTiles.vue';
import HomePlans from '../../Components/Landing/Home/HomePlans.vue';
import HomeShowcase from '../../Components/Landing/Home/HomeShowcase.vue';
import { usePublicPlans } from '../../Composables/usePublicPlans';
import { useLandingAnchors } from '../../Composables/useLandingAnchors';

const { plans, brand, founder, currency, pricesExcludeVat, isLoading, hasError, load } = usePublicPlans();
const { scrollToRouteHash } = useLandingAnchors();
const billing = ref('monthly');

onMounted(() => {
  load();
  scrollToRouteHash();
});
</script>

<template>
  <LandingShell :brand="brand">
    <HomeHero />
    <HomeAudience />
    <HomeShowcase />
    <HomeCoffeeBand />
    <HomeMoreTiles />
    <HomeDevicesSteps />
    <HomePlans
      v-model:billing="billing"
      :plans="plans"
      :is-loading="isLoading"
      :has-error="hasError"
      :currency="currency"
      :founder="founder"
      :prices-exclude-vat="pricesExcludeVat"
      @retry="load"
    />
    <LandingFaq />
    <LandingFinalCta />
  </LandingShell>
</template>
