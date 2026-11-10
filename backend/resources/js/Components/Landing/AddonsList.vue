<script setup>
import { computed } from 'vue';
import { trans } from '../../helpers/trans';
import { formatCatalogPrice } from '../../helpers/landingPricing';

const props = defineProps({
  addons: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
});

const currencyLabel = computed(() => {
  const key = `landing.currencies.${String(props.currency).toLowerCase()}`;
  const label = trans(key);
  return label === key ? props.currency : label;
});

const priceOf = (addon) => (props.billing === 'yearly' && addon.yearly_price ? addon.yearly_price : addon.unit_price);
</script>

<template>
  <section v-if="addons.length" class="space-y-4">
    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ $t('landing.addons_title') }}</h2>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="addon in addons"
        :key="addon.key"
        class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
      >
        <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ addon.name }}</h3>
        <p v-if="addon.description" class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {{ addon.description }}
        </p>
        <p class="text-sm font-black" style="color: var(--color-primary)" dir="ltr">
          {{ formatCatalogPrice(priceOf(addon)) }} {{ currencyLabel }}
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
            / {{ $t(billing === 'yearly' && addon.yearly_price ? 'landing.per_year' : 'landing.per_month') }}
          </span>
        </p>
      </article>
    </div>
  </section>
</template>
