<script setup>
import { ref, onMounted } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import LandingFaq from '../../Components/Landing/LandingFaq.vue';
import LandingFinalCta from '../../Components/Landing/LandingFinalCta.vue';
import PricingAddons from '../../Components/Landing/Pricing/PricingAddons.vue';
import PricingCompare from '../../Components/Landing/Pricing/PricingCompare.vue';
import PricingHeader from '../../Components/Landing/Pricing/PricingHeader.vue';
import PricingPlans from '../../Components/Landing/Pricing/PricingPlans.vue';
import { usePublicPlans } from '../../Composables/usePublicPlans';

const { plans, addons, brand, founder, currency, pricesExcludeVat, isLoading, hasError, load } = usePublicPlans();
const billing = ref('monthly');

onMounted(load);
</script>

<template>
  <LandingShell :brand="brand">
    <PricingHeader v-model:billing="billing" :plans="plans" :founder="founder" :is-loading="isLoading" />
    <PricingPlans
      :plans="plans"
      :billing="billing"
      :is-loading="isLoading"
      :has-error="hasError"
      :currency="currency"
      :founder="founder"
      :prices-exclude-vat="pricesExcludeVat"
      @retry="load"
    />
    <template v-if="!hasError">
      <PricingCompare
        :plans="plans"
        :billing="billing"
        :founder="founder"
        :currency="currency"
        :is-loading="isLoading"
      />
      <PricingAddons :addons="addons" :billing="billing" :currency="currency" :is-loading="isLoading" />
    </template>
    <LandingFaq />
    <LandingFinalCta />
  </LandingShell>
</template>
