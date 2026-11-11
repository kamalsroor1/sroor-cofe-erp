<script setup>
import { onMounted } from 'vue';
import { MessageCircle } from 'lucide-vue-next';
import LandingFaq from '../../Components/Landing/LandingFaq.vue';
import LandingFinalCta from '../../Components/Landing/LandingFinalCta.vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import MarketingPageHeader from '../../Components/Landing/MarketingPageHeader.vue';
import ContactChannels from '../../Components/Landing/Contact/ContactChannels.vue';
import { usePublicPlans } from '../../Composables/usePublicPlans';

// No contact-form backend: support phone / email come from the platform branding (GET /public/plans) when set.
const { brand, isLoading, hasError, catalog, load } = usePublicPlans();

onMounted(load);
</script>

<template>
  <LandingShell :brand="brand">
    <MarketingPageHeader
      :icon="MessageCircle"
      :kicker="$t('landing.pages.contact.kicker')"
      :title="$t('landing.pages.contact.title')"
      :subtitle="$t('landing.pages.contact.subtitle')"
    />
    <ContactChannels :brand="brand" :is-loading="isLoading || (!catalog && !hasError)" />
    <LandingFaq />
    <LandingFinalCta />
  </LandingShell>
</template>
