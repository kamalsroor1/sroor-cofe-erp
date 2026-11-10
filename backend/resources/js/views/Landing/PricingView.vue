<script setup>
import { ref, onMounted } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import PricingHero from '../../Components/Landing/PricingHero.vue';
import PlansSection from '../../Components/Landing/PlansSection.vue';
import AddonsList from '../../Components/Landing/AddonsList.vue';
import SupportFooter from '../../Components/Landing/SupportFooter.vue';
import { usePublicPlans } from '../../Composables/usePublicPlans';

const { plans, addons, brand, founder, currency, pricesExcludeVat, isLoading, hasError, load } = usePublicPlans();
const billing = ref('monthly');

onMounted(load);
</script>

<template>
  <LandingShell :brand-name="brand.name || ''">
    <div class="mx-auto max-w-6xl space-y-10 px-4 py-10 sm:px-6">
      <PricingHero v-model:billing="billing" :brand="brand" :is-loading="isLoading" />
      <PlansSection
        :plans="plans"
        :is-loading="isLoading"
        :has-error="hasError"
        :billing="billing"
        :currency="currency"
        :founder="founder"
        :prices-exclude-vat="pricesExcludeVat"
        @retry="load"
      />
      <AddonsList :addons="addons" :billing="billing" :currency="currency" />
    </div>
    <template #footer><SupportFooter :brand="brand" /></template>
  </LandingShell>
</template>
