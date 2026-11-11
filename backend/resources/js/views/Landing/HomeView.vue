<script setup>
import { onMounted, ref } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import HomeHero from '../../Components/Landing/HomeHero.vue';
import AudienceSection from '../../Components/Landing/AudienceSection.vue';
import FeatureGroups from '../../Components/Landing/FeatureGroups.vue';
import PlatformsSection from '../../Components/Landing/PlatformsSection.vue';
import StartSteps from '../../Components/Landing/StartSteps.vue';
import HomePlans from '../../Components/Landing/HomePlans.vue';
import LandingFaq from '../../Components/Landing/LandingFaq.vue';
import FinalCta from '../../Components/Landing/FinalCta.vue';
import SupportFooter from '../../Components/Landing/SupportFooter.vue';
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
  <LandingShell :brand-name="brand.name || ''">
    <div class="mx-auto max-w-6xl space-y-20 px-4 py-8 sm:px-6 sm:space-y-24">
      <HomeHero :brand="brand" :is-loading="isLoading" />
      <AudienceSection />
      <FeatureGroups />
      <PlatformsSection />
      <StartSteps />
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
      <FinalCta />
    </div>
    <template #footer><SupportFooter :brand="brand" /></template>
  </LandingShell>
</template>
