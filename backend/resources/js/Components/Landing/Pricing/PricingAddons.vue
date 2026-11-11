<script setup>
import { computed } from 'vue';
import {
  BarChart3,
  Boxes,
  Coffee,
  GraduationCap,
  HardDrive,
  Headphones,
  History,
  Package,
  Puzzle,
  Store,
  Truck,
  Users,
} from 'lucide-vue-next';
import Skeleton from '../../Common/Skeletons/Skeleton.vue';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import SectionTitle from '../SectionTitle.vue';
import { catalogCurrencyLabel, formatCatalogPrice } from '../../../helpers/landingPricing';

const props = defineProps({
  /** addons of GET /public/plans. */
  addons: { type: Array, default: () => [] },
  billing: { type: String, default: 'monthly' },
  currency: { type: String, default: '' },
  isLoading: { type: Boolean, default: false },
});

// Decorative only; an unknown add-on key gets the generic puzzle piece.
const ICONS = {
  'addon.store': Store,
  'addon.warehouse': Boxes,
  'addon.van': Truck,
  'addon.user': Users,
  'addon.storage_10gb': HardDrive,
  'addon.items_5k': Package,
  'mixes.manage': Coffee,
  'reports.advanced': BarChart3,
  'audit.logs': History,
  'addon.premium_support': Headphones,
  'service.onboarding': GraduationCap,
};

const currencyLabel = computed(() => catalogCurrencyLabel(props.currency));

/** One-time services have no period; recurring add-ons follow the billing toggle when a yearly price exists. */
const priceOf = (addon) => {
  if (addon.type === 'service') return { amount: addon.unit_price, period: 'landing.pricing.addons.one_time' };
  if (props.billing === 'yearly' && addon.yearly_price) {
    return { amount: addon.yearly_price, period: 'landing.per_year' };
  }
  return { amount: addon.unit_price, period: 'landing.per_month' };
};
</script>

<template>
  <section
    v-if="isLoading || addons.length"
    aria-labelledby="addons-title"
    class="bg-mk-bg px-5 pb-14 sm:px-6 lg:pb-24"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col gap-7">
      <SectionTitle
        id="addons-title"
        size="md"
        :title="$t('landing.pricing.addons.title')"
        :subtitle="$t('landing.pricing.addons.subtitle')"
      />

      <ul class="grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-3 lg:grid-cols-4" :aria-busy="isLoading">
        <template v-if="isLoading">
          <li v-for="n in 8" :key="n" class="flex flex-col gap-2.5 rounded-2xl border border-mk-line bg-mk-surface p-4">
            <Skeleton width="w-10" height="h-10" rounded="rounded-[11px]" />
            <Skeleton width="w-2/3" height="h-4" />
            <Skeleton width="w-1/2" height="h-3" />
          </li>
        </template>
        <li
          v-for="addon in addons"
          v-else
          :key="addon.key"
          class="flex flex-col gap-2 rounded-2xl border border-mk-line bg-mk-surface p-4"
        >
          <MarketingIconBadge :icon="ICONS[addon.key] || Puzzle" :size="40" />
          <h3 class="text-[15px] leading-[1.4] font-bold text-mk-ink">{{ addon.name }}</h3>
          <p v-if="addon.description" class="line-clamp-2 text-[13px] leading-[1.5] text-mk-muted">
            {{ addon.description }}
          </p>
          <p class="mt-auto text-[13px] font-medium text-mk-muted">
            + <span dir="ltr" class="font-bold text-mk-ink">{{ formatCatalogPrice(priceOf(addon).amount) }}</span>
            {{ currencyLabel }} / {{ $t(priceOf(addon).period) }}
          </p>
          <p v-if="addon.price_tiers?.length" class="text-xs font-medium text-mk-primary">
            {{ $t('landing.pricing.addons.volume_discount') }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
