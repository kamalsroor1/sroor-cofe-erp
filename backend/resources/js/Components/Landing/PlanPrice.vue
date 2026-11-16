<script setup>
import { computed } from 'vue';
import { formatDate } from '../../helpers/formatters';
import { hasVat, priceWithVat, vatRateLabel } from '../../helpers/landingCatalog';
import { formatCatalogPrice } from '../../helpers/landingPricing';

const props = defineProps({
  /** planDisplayPrice() result. */
  price: { type: Object, required: true },
  currencyLabel: { type: String, default: '' },
  yearly: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  /** Whole-percent saving of paying yearly (0 = none). */
  saving: { type: Number, default: 0 },
  founder: { type: Object, default: () => ({ available: false }) },
  trialDays: { type: Number, default: 0 },
  /** Platform VAT percent from the API ('14.000'); zero hides the VAT lines. */
  taxRate: { type: String, default: '0.000' },
  pricesExcludeVat: { type: Boolean, default: false },
});

const showVat = computed(() => hasVat(props.taxRate));
const grossPrice = computed(() => formatCatalogPrice(priceWithVat(props.price.shown, props.taxRate)));
const endsOn = computed(() => (props.price.promoEndsAt ? formatDate(props.price.promoEndsAt) : ''));
const small = computed(() => (props.compact ? 'text-[13px]' : 'text-sm'));
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <p v-if="price.compareAt" class="text-sm font-bold text-mk-faint line-through" data-testid="plan-compare-at">
      <span dir="ltr">{{ formatCatalogPrice(price.compareAt) }}</span> {{ currencyLabel }}
    </p>
    <p class="flex flex-wrap items-baseline gap-x-1.5">
      <span class="font-bold text-mk-ink" :class="compact ? 'text-[34px]' : 'text-[44px]'" dir="ltr">
        {{ formatCatalogPrice(price.shown) }}
      </span>
      <span class="font-medium text-mk-muted" :class="compact ? 'text-[13px]' : 'text-[15px]'">
        {{ currencyLabel }} / {{ $t(yearly ? 'landing.per_year' : 'landing.per_month') }}
      </span>
    </p>
    <p v-if="showVat" class="text-xs text-mk-faint">
      {{ $t('landing.pricing.excl_vat_note') }}
      <span class="text-mk-muted">
        {{ $t('landing.pricing.incl_vat', { rate: vatRateLabel(taxRate) }) }}:
        <span dir="ltr" class="font-bold">{{ grossPrice }}</span> {{ currencyLabel }}
      </span>
    </p>
    <p v-if="trialDays" class="font-medium text-mk-primary" :class="small">
      {{ $t('landing.trial_days', { count: trialDays }) }}
    </p>
    <div v-if="saving > 0 || price.hasFounder || price.hasPromo" class="flex flex-wrap gap-1.5 pt-0.5">
      <span v-if="saving > 0" class="rounded-full bg-mk-soft px-2.5 py-1 text-[11px] font-bold text-mk-soft-ink">
        {{ $t('landing.pricing.save_percent', { percent: saving }) }}
      </span>
      <span
        v-if="price.hasPromo && price.discountPercent > 0"
        class="rounded-full bg-mk-accent px-2.5 py-1 text-[11px] font-bold text-mk-accent-ink"
        data-testid="plan-discount-badge"
      >
        {{ $t('landing.pricing.discount_percent', { percent: price.discountPercent }) }}
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
    <p v-if="endsOn" class="text-xs font-medium text-mk-warn-ink" data-testid="plan-offer-ends">
      {{ $t('landing.pricing.offer_ends', { date: endsOn }) }}
    </p>
    <p v-if="price.hasFounder && founder.months" class="text-xs font-medium text-mk-muted">
      {{ $t('landing.pricing.founder_price_months', { count: founder.months }) }}
    </p>
    <p v-if="pricesExcludeVat" class="sr-only">{{ $t('landing.prices_exclude_vat') }}</p>
  </div>
</template>
