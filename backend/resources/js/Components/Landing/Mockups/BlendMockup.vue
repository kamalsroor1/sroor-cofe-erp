<script setup>
import { Coffee } from 'lucide-vue-next';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import MockChip from './MockChip.vue';
import { MOCK_BLEND } from './mockData';

const selectors = [
  { key: 'roast', options: MOCK_BLEND.roasts, selected: MOCK_BLEND.roast },
  { key: 'grind', options: MOCK_BLEND.grinds, selected: MOCK_BLEND.grind },
];
</script>

<template>
  <!-- Fluid card (fills its box up to the design's 400px); decorative, so hidden from assistive tech. -->
  <div
    class="flex w-full max-w-[400px] flex-col gap-3.5 rounded-[20px] bg-mk-app-surface p-5 select-none mk-shadow-float"
    aria-hidden="true"
  >
    <div class="flex items-center gap-2.5">
      <MarketingIconBadge :icon="Coffee" :size="36" />
      <span class="flex-1 text-base font-extrabold text-mk-app-ink">{{ $t('landing.mock.blend.title') }}</span>
      <span class="text-xs font-bold text-mk-app-muted">{{ $t('landing.mock.kg', { qty: MOCK_BLEND.weight }) }}</span>
    </div>

    <div v-for="part in MOCK_BLEND.parts" :key="part.key" class="flex flex-col gap-1.5">
      <div class="flex items-center justify-between text-[13px] text-mk-app-ink">
        <span class="font-bold">{{ $t(`landing.mock.products.${part.key}`) }}</span>
        <span class="font-extrabold">{{ $t('landing.mock.percent', { value: part.share }) }}</span>
      </div>
      <span class="flex h-2 overflow-hidden rounded bg-mk-app-line">
        <span class="rounded" :style="{ width: `${part.share}%`, backgroundColor: part.color }"></span>
      </span>
    </div>

    <div v-for="selector in selectors" :key="selector.key" class="flex flex-col gap-1.5">
      <span class="text-[11px] font-medium text-mk-app-muted">{{ $t(`landing.mock.blend.${selector.key}`) }}</span>
      <div class="flex flex-wrap gap-1.5">
        <MockChip
          v-for="option in selector.options"
          :key="option"
          :tone="option === selector.selected ? 'primary' : 'muted'"
          :height="28"
        >
          {{ $t(`landing.mock.blend.${option}`) }}
        </MockChip>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5 rounded-[10px] bg-mk-app-bg p-3">
      <div class="flex flex-col gap-0.5">
        <span class="text-[11px] font-medium text-mk-app-muted">{{ $t('landing.mock.blend.cost') }}</span>
        <span class="text-[15px] font-extrabold text-mk-app-ink">{{
          $t('landing.mock.money', { amount: MOCK_BLEND.cost })
        }}</span>
      </div>
      <div class="flex flex-col gap-0.5">
        <span class="text-[11px] font-medium text-mk-app-muted">{{ $t('landing.mock.blend.sale_price') }}</span>
        <span class="text-[15px] font-extrabold text-mk-primary">{{
          $t('landing.mock.money', { amount: MOCK_BLEND.price })
        }}</span>
      </div>
    </div>

    <span
      class="flex h-[42px] items-center justify-center rounded-[10px] bg-mk-app-cta text-[13px] font-bold text-white"
    >
      {{ $t('landing.mock.blend.add_to_invoice') }}
    </span>
  </div>
</template>
