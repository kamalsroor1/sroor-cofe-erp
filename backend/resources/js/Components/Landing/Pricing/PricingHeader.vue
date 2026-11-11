<script setup>
import { computed } from 'vue';
import { Sparkles, Tags } from 'lucide-vue-next';
import BillingToggle from '../BillingToggle.vue';
import MarketingPageHeader from '../MarketingPageHeader.vue';
import { catalogYearlySaving } from '../../../helpers/landingCatalog';
import { useTrans } from '../../../Composables/useTrans';

const props = defineProps({
  plans: { type: Array, default: () => [] },
  founder: { type: Object, default: () => ({ available: false }) },
  isLoading: { type: Boolean, default: false },
});

const billing = defineModel('billing', { type: String, default: 'monthly' });
const { t } = useTrans();

const saving = computed(() => catalogYearlySaving(props.plans));

// Founder offer: only while the API says slots are left; months / slots are shown when known.
const founderText = computed(() => {
  const { available, months, slots_left: slots } = props.founder || {};
  if (!available || !props.plans.length) return '';
  const hasSlots = slots !== null && slots !== undefined;
  if (months && hasSlots) return t('landing.pricing.founder_banner', { months, count: slots });
  if (months) return t('landing.pricing.founder_price_months', { count: months });
  return hasSlots ? t('landing.founder_slots_left', { count: slots }) : t('landing.founder_badge');
});
</script>

<template>
  <MarketingPageHeader
    :kicker="$t('landing.nav.pricing')"
    :icon="Tags"
    :title="$t('landing.pricing.title')"
    :subtitle="$t('landing.pricing.subtitle')"
  >
    <BillingToggle
      v-if="isLoading || plans.length"
      v-model="billing"
      on-dark
      :save-percent="saving.percent"
      :save-up-to="saving.upTo"
    />
    <p
      v-if="founderText"
      class="inline-flex min-h-9 items-center gap-2 rounded-full border border-mk-accent/35 bg-mk-accent/15 px-4 py-1.5 text-sm font-medium text-mk-accent"
    >
      <Sparkles class="size-4 shrink-0" aria-hidden="true" />
      {{ founderText }}
    </p>
  </MarketingPageHeader>
</template>
