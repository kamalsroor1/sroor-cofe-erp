<script setup>
import { computed } from 'vue';
import { AlertTriangle, ArrowLeft, Sparkles } from 'lucide-vue-next';
import ErrorState from '../Common/ErrorState.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import MarketingButton from './MarketingButton.vue';
import MarketingIconBadge from './MarketingIconBadge.vue';
import PlanCard from './PlanCard.vue';
import { distinctFirstFeatures } from '../../helpers/landingCatalog';

const props = defineProps({
  plans: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  isLoading: { type: Boolean, default: false },
  hasError: { type: Boolean, default: false },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  /** Feature bullets per card (0 = all). */
  maxFeatures: { type: Number, default: 3 },
  showDetails: { type: Boolean, default: false },
  /** Limit rows per card when showDetails (0 = all). */
  maxLimits: { type: Number, default: 0 },
  /** List each plan's distinctive features (not in every plan) before the shared ones. */
  distinctFirst: { type: Boolean, default: false },
});

defineEmits(['retry']);

/*
 * Narrow screens: a horizontal scroll-snap carousel, the popular plan first and the next card peeking.
 * Wide screens: 1–3 plans in a centred row of 380px cards, 4 in four columns, 5 in five compact columns
 * (only from xl, below that five cards stay a carousel). Class strings are literal for Tailwind.
 */
const TRACK =
  'flex snap-x snap-mandatory gap-3.5 overflow-x-auto -mx-5 px-5 scroll-px-5 pt-2 pb-8 sm:-mx-6 sm:px-6 sm:scroll-px-6 mk-no-scrollbar';
const CARD = 'w-[300px] max-w-[82vw] shrink-0 snap-start';
const LAYOUTS = {
  row: {
    track: 'lg:mx-0 lg:justify-center lg:gap-6 lg:overflow-visible lg:px-0',
    card: 'lg:w-auto lg:max-w-[380px] lg:flex-1 lg:shrink',
    popular: 'order-first lg:order-none',
  },
  four: {
    track: 'lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-5 lg:overflow-visible lg:px-0',
    card: 'lg:w-auto lg:max-w-none',
    popular: 'order-first lg:order-none',
  },
  five: {
    track: 'xl:mx-0 xl:grid xl:grid-cols-5 xl:gap-[18px] xl:overflow-visible xl:px-0',
    card: 'xl:w-auto xl:max-w-none',
    popular: 'order-first xl:order-none',
  },
};

const layout = computed(() => {
  if (props.plans.length >= 5) return LAYOUTS.five;
  return props.plans.length === 4 ? LAYOUTS.four : LAYOUTS.row;
});
const isSingle = computed(() => props.plans.length === 1);
const isCompact = computed(() => props.plans.length >= 4);
const featureLists = computed(() =>
  Object.fromEntries(
    props.plans.map((plan) => [plan.code, props.distinctFirst ? distinctFirstFeatures(plan, props.plans) : null])
  )
);
</script>

<template>
  <div v-if="isLoading" :class="[TRACK, LAYOUTS.row.track]" aria-busy="true">
    <div
      v-for="n in 3"
      :key="n"
      class="flex flex-col gap-4 rounded-3xl border border-mk-line bg-mk-surface p-7"
      :class="[CARD, LAYOUTS.row.card]"
    >
      <Skeleton width="w-1/2" height="h-6" />
      <Skeleton width="w-2/3" height="h-11" />
      <Skeleton width="w-1/3" height="h-4" />
      <Skeleton height="h-px" rounded="rounded-none" />
      <Skeleton v-for="m in 3" :key="m" height="h-4" />
      <Skeleton height="h-[46px]" rounded="rounded-[14px]" />
    </div>
  </div>

  <ErrorState
    v-else-if="hasError"
    :icon="AlertTriangle"
    :title="$t('landing.errors.plans_unavailable')"
    @retry="$emit('retry')"
  />

  <div
    v-else-if="!plans.length"
    class="mx-auto flex w-full max-w-[640px] flex-col items-center gap-3.5 rounded-3xl border-2 border-dashed border-mk-line bg-mk-surface px-7 py-10 text-center"
  >
    <MarketingIconBadge :icon="Sparkles" :size="56" />
    <h3 class="text-[22px] font-bold text-mk-ink">{{ $t('landing.home.plans_empty.title') }}</h3>
    <p class="max-w-md text-base leading-[1.6] text-mk-muted">{{ $t('landing.home.plans_empty.body') }}</p>
    <MarketingButton to="/signup" :icon="ArrowLeft">{{ $t('landing.request_trial') }}</MarketingButton>
  </div>

  <div v-else-if="isSingle" class="flex justify-center pt-2 pb-8">
    <div class="w-full max-w-[380px]">
      <PlanCard
        :plan="plans[0]"
        :billing="billing"
        :currency="currency"
        :founder="founder"
        :show-details="showDetails"
        :max-features="maxFeatures"
        :max-limits="maxLimits"
        :feature-list="featureLists[plans[0].code]"
      />
    </div>
  </div>

  <ul v-else :class="[TRACK, layout.track]" :aria-label="$t('landing.home.plans_title')" tabindex="0">
    <li v-for="plan in plans" :key="plan.code" :class="[CARD, layout.card, plan.is_popular ? layout.popular : '']">
      <PlanCard
        :plan="plan"
        :billing="billing"
        :currency="currency"
        :founder="founder"
        :show-details="showDetails"
        :compact="isCompact"
        :max-features="maxFeatures"
        :max-limits="maxLimits"
        :feature-list="featureLists[plan.code]"
      />
    </li>
  </ul>
</template>
