<script setup>
import { computed } from 'vue';
import CheckItem from './CheckItem.vue';
import MarketingButton from './MarketingButton.vue';
import PlanIncludedAddons from './PlanIncludedAddons.vue';
import PlanPrice from './PlanPrice.vue';
import { trans } from '../../helpers/trans';
import {
  LIMIT_KEYS,
  catalogCurrencyLabel,
  formatCatalogCount,
  yearlySavingPercent,
} from '../../helpers/landingPricing';
import { planDisplayPrice } from '../../helpers/landingCatalog';

const props = defineProps({
  /** One plan of GET /public/plans. */
  plan: { type: Object, required: true },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  taxRate: { type: String, default: '0.000' },
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

    <PlanPrice
      :price="price"
      :currency-label="currencyLabel"
      :yearly="isYearly"
      :compact="compact"
      :saving="saving"
      :founder="founder"
      :trial-days="plan.trial_days || 0"
      :tax-rate="taxRate"
      :prices-exclude-vat="pricesExcludeVat"
    />

    <PlanIncludedAddons :addons="plan.included_addons || []" :compact="compact" />

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
