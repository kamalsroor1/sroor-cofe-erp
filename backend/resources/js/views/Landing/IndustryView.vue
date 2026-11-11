<script setup>
import { computed, onMounted, watchPostEffect } from 'vue';
import { useRoute } from 'vue-router';
import LandingFinalCta from '../../Components/Landing/LandingFinalCta.vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import HomeShowcase from '../../Components/Landing/Home/HomeShowcase.vue';
import IndustryDailyCards from '../../Components/Landing/Industry/IndustryDailyCards.vue';
import IndustryHero from '../../Components/Landing/Industry/IndustryHero.vue';
import IndustryOthers from '../../Components/Landing/Industry/IndustryOthers.vue';
import { INDUSTRY_PAGES } from '../../Components/Landing/Industry/industryPages';
import { LANDING_INDUSTRIES, industryLabelKey } from '../../Composables/useLandingNav';
import { usePublicPlans } from '../../Composables/usePublicPlans';
import { useTrans } from '../../Composables/useTrans';
import { useAppConfigStore } from '../../stores/appConfig';

const route = useRoute();
const { t } = useTrans();
const appConfig = useAppConfigStore();
const { brand, load } = usePublicPlans();

// The route only matches coffee|retail|wholesale|vans; App keys views by fullPath, so a switch remounts.
const industry = computed(() => LANDING_INDUSTRIES.find((item) => item.key === route.params.industry));
const page = computed(() => INDUSTRY_PAGES[industry.value.key]);

// Per-industry tab title (the router sets the generic one first; this runs after it and on language switch).
watchPostEffect(() => {
  const appName = appConfig.platformName || t('nav.page_titles.default_app_name');
  document.title = `${t(industryLabelKey(industry.value.key))} - ${appName}`;
});

onMounted(load);
</script>

<template>
  <LandingShell :brand="brand">
    <IndustryHero :industry="industry.key" :icon="industry.icon" :visual="page.visual" />
    <IndustryDailyCards :industry="industry.key" :items="page.items" />
    <HomeShowcase />
    <IndustryOthers :current="industry.key" />
    <LandingFinalCta />
  </LandingShell>
</template>
