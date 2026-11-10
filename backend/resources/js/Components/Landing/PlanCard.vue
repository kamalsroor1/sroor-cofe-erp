<script setup>
import { computed } from 'vue';
import { Check, Sparkles } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import { trans } from '../../helpers/trans';
import { LIMIT_KEYS, formatCatalogCount, formatCatalogPrice, yearlySavingPercent } from '../../helpers/landingPricing';

const props = defineProps({
  plan: { type: Object, required: true },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
  founder: { type: Object, default: () => ({ available: false }) },
  pricesExcludeVat: { type: Boolean, default: false },
  showDetails: { type: Boolean, default: true },
});

const isYearly = computed(() => props.billing === 'yearly');
const regularPrice = computed(() => (isYearly.value ? props.plan.price_yearly : props.plan.price_monthly));
const founderPrice = computed(() =>
  isYearly.value ? props.plan.founder_price_yearly : props.plan.founder_price_monthly
);
const hasFounderPrice = computed(() => props.founder?.available && !!founderPrice.value);
const shownPrice = computed(() => (hasFounderPrice.value ? founderPrice.value : regularPrice.value));
const saving = computed(() =>
  isYearly.value ? yearlySavingPercent(props.plan.price_monthly, props.plan.price_yearly) : 0
);
const currencyLabel = computed(() => {
  const key = `landing.currencies.${String(props.currency).toLowerCase()}`;
  const label = trans(key);
  return label === key ? props.currency : label;
});

const limits = computed(() =>
  LIMIT_KEYS.map((key) => {
    const value = props.plan.limits?.[key];
    return {
      key,
      label: trans(`landing.limits.${key}`),
      value: value === null || value === undefined ? trans('landing.unlimited') : formatCatalogCount(value),
    };
  })
);
</script>

<template>
  <article
    class="relative flex flex-col gap-4 rounded-3xl border bg-white p-5 shadow-xs dark:bg-slate-900 sm:p-6"
    :class="plan.is_popular ? 'border-2' : 'border-slate-200 dark:border-slate-800'"
    :style="plan.is_popular ? 'border-color: var(--color-primary)' : ''"
    :data-testid="`plan-card-${plan.code}`"
  >
    <span
      v-if="plan.is_popular"
      class="absolute -top-3 end-5 inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-black text-slate-950"
      style="background: var(--color-primary)"
    >
      <Sparkles class="h-3.5 w-3.5" />
      {{ $t('landing.popular') }}
    </span>

    <header class="space-y-1">
      <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ plan.name }}</h3>
      <p v-if="plan.description" class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
        {{ plan.description }}
      </p>
    </header>

    <div class="space-y-1">
      <div v-if="hasFounderPrice" class="text-xs font-bold text-slate-400 line-through" dir="ltr">
        {{ formatCatalogPrice(regularPrice) }} {{ currencyLabel }}
      </div>
      <div class="flex flex-wrap items-baseline gap-x-1.5">
        <span class="text-3xl font-black text-slate-900 dark:text-white" dir="ltr">
          {{ formatCatalogPrice(shownPrice) }}
        </span>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
          {{ currencyLabel }} / {{ $t(isYearly ? 'landing.per_year' : 'landing.per_month') }}
        </span>
      </div>
      <p v-if="pricesExcludeVat" class="text-[11px] font-bold text-slate-400">{{ $t('landing.prices_exclude_vat') }}</p>

      <div class="flex flex-wrap gap-1.5 pt-1">
        <span
          v-if="saving > 0"
          class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-black text-emerald-600 dark:text-emerald-400"
        >
          {{ $t('landing.pricing.save_percent', { percent: saving }) }}
        </span>
        <span
          v-if="hasFounderPrice"
          class="rounded-full px-2.5 py-1 text-[11px] font-black"
          style="background: var(--color-primary-light); color: var(--color-primary)"
        >
          {{ $t('landing.founder_badge') }}
          <template v-if="founder.slots_left !== null && founder.slots_left !== undefined">
            - {{ $t('landing.founder_slots_left', { count: founder.slots_left }) }}
          </template>
        </span>
      </div>
      <p v-if="hasFounderPrice && founder.months" class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
        {{ $t('landing.pricing.founder_price_months', { count: founder.months }) }}
      </p>
      <p v-if="plan.trial_days" class="text-[11px] font-black text-emerald-600 dark:text-emerald-400">
        {{ $t('landing.trial_days', { count: plan.trial_days }) }}
      </p>
    </div>

    <BaseButton
      :to="{ path: '/signup', query: { plan: plan.code } }"
      :variant="plan.is_popular ? 'primary' : 'outline'"
      size="md"
      full-width
    >
      {{ $t('landing.request_trial') }}
    </BaseButton>

    <template v-if="showDetails">
      <ul class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-800">
        <li v-for="limit in limits" :key="limit.key" class="flex items-center justify-between gap-3 text-xs">
          <span class="font-bold text-slate-500 dark:text-slate-400">{{ limit.label }}</span>
          <span class="font-black text-slate-900 dark:text-white">{{ limit.value }}</span>
        </li>
      </ul>

      <div v-if="plan.features?.length" class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-800">
        <h4 class="text-xs font-black text-slate-700 dark:text-slate-200">{{ $t('landing.features_title') }}</h4>
        <ul class="space-y-2">
          <li v-for="feature in plan.features" :key="feature.key" class="flex items-start gap-2 text-xs">
            <Check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
            <span class="font-bold text-slate-700 dark:text-slate-300" :title="feature.description || undefined">
              {{ feature.name }}
            </span>
          </li>
        </ul>
      </div>
    </template>
  </article>
</template>
