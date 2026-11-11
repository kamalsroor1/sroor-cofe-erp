<script setup>
import BaseButton from '../Common/BaseButton.vue';
import BillingToggle from './BillingToggle.vue';
import LandingSection from './LandingSection.vue';
import PlansSection from './PlansSection.vue';

defineProps({
  plans: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  pricesExcludeVat: { type: Boolean, default: false },
});

defineEmits(['update:billing', 'retry']);
</script>

<template>
  <LandingSection id="plans" :title="$t('landing.home.plans_title')" :subtitle="$t('landing.home.plans_subtitle')">
    <div class="flex justify-center">
      <BillingToggle :model-value="billing" @update:model-value="$emit('update:billing', $event)" />
    </div>
    <PlansSection
      :plans="plans"
      :billing="billing"
      :is-loading="isLoading"
      :has-error="hasError"
      :currency="currency"
      :founder="founder"
      :prices-exclude-vat="pricesExcludeVat"
      :show-details="false"
      @retry="$emit('retry')"
    />
    <div class="flex justify-center">
      <BaseButton :to="{ name: 'landing.pricing' }" variant="outline" size="md">
        {{ $t('landing.home.see_all_plans') }}
      </BaseButton>
    </div>
  </LandingSection>
</template>
