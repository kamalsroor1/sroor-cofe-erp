<script setup>
import { computed } from 'vue';
import CheckItem from './CheckItem.vue';
import MarketingButton from './MarketingButton.vue';
import { trans } from '../../helpers/trans';
import {
  LIMIT_KEYS,
  catalogCurrencyLabel,
  formatCatalogCount,
  formatCatalogPrice,
  yearlySavingPercent,
} from '../../helpers/landingPricing';
import { planDisplayPrice } from '../../helpers/landingCatalog';

const props = defineProps({
  /** One plan of GET /public/plans. */
  plan: { type: Object, required: true },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  pricesExcludeVat: { type: Boolean, default: false },
  /** Description + limits block (pricing page). */
  showDetails: { type: Boolean, default: true },
  /** Smaller type and padding for 4–5 plans in one row. */
  compact: { type: Boolean, default: false },
  /** Cap the feature bullets (0 = all). */
  maxFeatures: { type: Number, default: 0 },
  /** Cap the limit rows of the details block (0 = all). */
  maxLimits: { type: Number, default: 0 },
  /** Feature order override (e.g. distinctive features first); null = the plan's own order. */
  featureList: { type: Array, default: null },
});

const isYearly = computed(() => props.billing === 'yearly');
const price = computed(() => planDisplayPrice(props.plan, props.billing, props.founder));
const saving = computed(() =>
  isYearly.value ? yearlySavingPercent(props.plan.price_monthly, props.plan.price_yearly) : 0
);
const currencyLabel = computed(() => catalogCurrencyLabel(props.currency));

const limits = computed(() =>
  (props.maxLimits > 0 ? LIMIT_KEYS.slice(0, props.maxLimits) : LIMIT_KEYS).map((key) => {
    const value = props.plan.limits?.[key];
    return {
      key,
      label: trans(`landing.limits.${key}`),
      value: value === null || value === undefined ? trans('landing.unlimited') : formatCatalogCount(value),
    };
  })
);

const features = computed(() => {
  const list = props.featureList || props.plan.features || [];
  return props.maxFeatures > 0 ? list.slice(0, props.maxFeatures) : list;
});
</script>

<template>
  <article
    class="relative flex h-full flex-col bg-mk-surface"
    :class="[
      compact ? 'gap-3.5 rounded-[20px] p-5' : 'gap-4 rounded-3xl p-6 sm:p-7',
      plan.is_popular ? 'border-2 border-mk-accent mk-shadow-card' : 'border border-mk-line',
    ]"
    :data-testid="`plan-card-${plan.code}`"
  >
    <span
      v-if="plan.is_popular"
      class="inline-flex h-7 w-fit items-center rounded-full bg-mk-accent px-3 text-xs font-bold text-mk-accent-ink"
    >
      {{ $t('landing.popular') }}
    </span>

    <header class="flex flex-col gap-1.5">
      <h3 class="font-bold text-mk-ink" :class="compact ? 'text-[19px]' : 'text-[22px]'">{{ plan.name }}</h3>
      <p
        v-if="showDetails && plan.description"
        class="leading-[1.55] text-mk-muted"
        :class="compact ? 'text-[13px]' : 'text-sm'"
      >
        {{ plan.description }}
      </p>
    </header>

    <div class="flex flex-col gap-1.5">
      <p v-if="price.hasFounder" class="text-sm font-bold text-mk-faint line-through">
        <span dir="ltr">{{ formatCatalogPrice(price.regular) }}</span> {{ currencyLabel }}
      </p>
      <p class="flex flex-wrap items-baseline gap-x-1.5">
        <span class="font-bold text-mk-ink" :class="compact ? 'text-[34px]' : 'text-[44px]'" dir="ltr">
          {{ formatCatalogPrice(price.shown) }}
        </span>
        <span class="font-medium text-mk-muted" :class="compact ? 'text-[13px]' : 'text-[15px]'">
          {{ currencyLabel }} / {{ $t(isYearly ? 'landing.per_year' : 'landing.per_month') }}
        </span>
      </p>
      <p v-if="plan.trial_days" class="font-medium text-mk-primary" :class="compact ? 'text-[13px]' : 'text-sm'">
        {{ $t('landing.trial_days', { count: plan.trial_days }) }}
      </p>
      <div v-if="saving > 0 || price.hasFounder" class="flex flex-wrap gap-1.5 pt-0.5">
        <span v-if="saving > 0" class="rounded-full bg-mk-soft px-2.5 py-1 text-[11px] font-bold text-mk-soft-ink">
          {{ $t('landing.pricing.save_percent', { percent: saving }) }}
        </span>
        <span
          v-if="price.hasFounder"
          class="rounded-full bg-mk-accent-soft px-2.5 py-1 text-[11px] font-bold text-mk-warn-ink"
        >
          <template v-if="founder.slots_left !== null && founder.slots_left !== undefined">
            {{ $t('landing.founder_slots_left', { count: founder.slots_left }) }}
          </template>
          <template v-else>{{ $t('landing.founder_badge') }}</template>
        </span>
      </div>
      <p v-if="price.hasFounder && founder.months" class="text-xs font-medium text-mk-muted">
        {{ $t('landing.pricing.founder_price_months', { count: founder.months }) }}
      </p>
      <p v-if="pricesExcludeVat" class="text-xs text-mk-faint">{{ $t('landing.prices_exclude_vat') }}</p>
    </div>

    <hr class="border-mk-line" />

    <dl v-if="showDetails" class="flex flex-col gap-2 rounded-xl bg-mk-bg p-3">
      <div v-for="limit in limits" :key="limit.key" class="flex items-center justify-between gap-3 text-[13px]">
        <dt class="font-medium text-mk-muted">{{ limit.label }}</dt>
        <dd class="font-bold text-mk-ink">{{ limit.value }}</dd>
      </div>
    </dl>

    <ul v-if="features.length" class="flex flex-col" :class="compact ? 'gap-2.5' : 'gap-3'">
      <CheckItem v-for="feature in features" :key="feature.key" :size="compact ? 'sm' : 'md'">
        <span :title="feature.description || undefined">{{ feature.name }}</span>
      </CheckItem>
    </ul>

    <MarketingButton
      :to="{ path: '/signup', query: { plan: plan.code } }"
      :variant="plan.is_popular ? 'accent' : 'outline'"
      :size="compact ? 'sm' : 'md'"
      block
      class="mt-auto"
    >
      {{ $t('landing.request_workspace') }}
    </MarketingButton>
  </article>
</template>
