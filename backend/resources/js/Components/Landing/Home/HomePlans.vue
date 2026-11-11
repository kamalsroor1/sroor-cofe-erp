<script setup>
import { ArrowLeft } from 'lucide-vue-next';
import BillingToggle from '../BillingToggle.vue';
import PlansShowcase from '../PlansShowcase.vue';
import SectionTitle from '../SectionTitle.vue';

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
  <section
    id="plans"
    aria-labelledby="plans-title"
    tabindex="-1"
    class="scroll-mt-24 bg-mk-bg px-5 py-16 focus:outline-none sm:px-6 lg:py-28"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col items-center gap-8">
      <SectionTitle
        id="plans-title"
        :title="$t('landing.home.plans_title')"
        :subtitle="$t('landing.home.plans_tagline')"
      >
        <BillingToggle
          v-if="isLoading || plans.length"
          class="mt-1"
          :model-value="billing"
          @update:model-value="$emit('update:billing', $event)"
        />
      </SectionTitle>

      <div class="w-full">
        <PlansShowcase
          :plans="plans"
          :billing="billing"
          :is-loading="isLoading"
          :has-error="hasError"
          :currency="currency"
          :founder="founder"
          @retry="$emit('retry')"
        />
      </div>

      <div class="flex flex-col items-center gap-2 text-center">
        <p v-if="pricesExcludeVat && plans.length" class="text-sm text-mk-faint">
          {{ $t('landing.prices_exclude_vat') }}
        </p>
        <router-link
          :to="{ name: 'landing.pricing' }"
          class="inline-flex min-h-11 items-center gap-2 rounded-lg text-base font-bold text-mk-primary hover:underline focus-visible:outline-2 focus-visible:outline-mk-primary"
        >
          {{ $t('landing.home.see_all_plans') }}
          <ArrowLeft class="size-4 ltr:rotate-180" aria-hidden="true" />
        </router-link>
      </div>
    </div>
  </section>
</template>
