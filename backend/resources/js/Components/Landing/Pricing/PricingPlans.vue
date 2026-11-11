<script setup>
import { ArrowDown } from 'lucide-vue-next';
import PlansShowcase from '../PlansShowcase.vue';

defineProps({
  plans: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  pricesExcludeVat: { type: Boolean, default: false },
});

defineEmits(['retry']);

// The page scrolls inside LandingShell, so the in-page jump is explicit (no router hash navigation).
const goToCompare = () => {
  const section = document.getElementById('compare');
  if (!section) return;
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  section.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
  section.focus({ preventScroll: true });
};
</script>

<template>
  <section :aria-label="$t('landing.home.plans_title')" class="bg-mk-bg px-5 pt-8 pb-4 sm:px-6 lg:pt-12">
    <div class="mx-auto flex max-w-[1200px] flex-col items-center gap-3">
      <div class="w-full">
        <PlansShowcase
          :plans="plans"
          :billing="billing"
          :is-loading="isLoading"
          :has-error="hasError"
          :currency="currency"
          :founder="founder"
          :max-features="5"
          :max-limits="3"
          show-details
          distinct-first
          @retry="$emit('retry')"
        />
      </div>
      <p v-if="pricesExcludeVat && plans.length" class="text-center text-[13px] leading-[1.5] text-mk-faint">
        {{ $t('landing.prices_exclude_vat') }}
      </p>
      <button
        v-if="plans.length >= 2"
        type="button"
        class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 text-[15px] font-bold text-mk-primary hover:underline focus-visible:outline-2 focus-visible:outline-mk-primary"
        @click="goToCompare"
      >
        {{ $t('landing.pricing.compare_link') }}
        <ArrowDown class="size-4" aria-hidden="true" />
      </button>
    </div>
  </section>
</template>
