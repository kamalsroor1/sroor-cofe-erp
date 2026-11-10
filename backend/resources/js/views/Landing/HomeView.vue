<script setup>
import { onMounted } from 'vue';
import LandingShell from '../../Components/Landing/LandingShell.vue';
import HomeHero from '../../Components/Landing/HomeHero.vue';
import ValuePoints from '../../Components/Landing/ValuePoints.vue';
import PlansSection from '../../Components/Landing/PlansSection.vue';
import SupportFooter from '../../Components/Landing/SupportFooter.vue';
import BaseButton from '../../Components/Common/BaseButton.vue';
import { usePublicPlans } from '../../Composables/usePublicPlans';

const { plans, brand, founder, currency, pricesExcludeVat, isLoading, hasError, load } = usePublicPlans();

onMounted(load);
</script>

<template>
  <LandingShell :brand-name="brand.name || ''">
    <div class="mx-auto max-w-6xl space-y-14 px-4 py-8 sm:px-6">
      <HomeHero :brand="brand" :is-loading="isLoading" />
      <ValuePoints />

      <section class="space-y-5">
        <h2 class="text-center text-xl font-black text-slate-900 dark:text-white">
          {{ $t('landing.home.teaser_title') }}
        </h2>
        <PlansSection
          :plans="plans"
          :is-loading="isLoading"
          :has-error="hasError"
          :currency="currency"
          :founder="founder"
          :prices-exclude-vat="pricesExcludeVat"
          :show-details="false"
          @retry="load"
        />
        <div class="flex justify-center">
          <BaseButton to="/pricing" variant="outline" size="md">{{ $t('landing.home.see_all_plans') }}</BaseButton>
        </div>
      </section>

      <section
        class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 text-center dark:border-slate-800 dark:bg-slate-900"
      >
        <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('landing.home.cta_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('landing.home.cta_subtitle') }}</p>
        <BaseButton to="/signup" variant="primary" size="lg">{{ $t('landing.request_trial') }}</BaseButton>
      </section>
    </div>
    <template #footer><SupportFooter :brand="brand" /></template>
  </LandingShell>
</template>
