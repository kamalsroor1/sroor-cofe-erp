<script setup>
import { ArrowLeft, Truck } from 'lucide-vue-next';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import { MOCK_TRANSFER } from './mockData';
</script>

<template>
  <div
    class="flex w-[230px] flex-col gap-2.5 rounded-[14px] border border-mk-app-line bg-mk-app-surface p-3.5 mk-shadow-card"
  >
    <div class="flex items-center gap-2.5">
      <MarketingIconBadge :icon="Truck" :size="34" />
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="text-[13px] font-extrabold text-mk-app-ink">{{ $t('landing.mock.inventory.transfer') }}</span>
        <span class="flex items-center gap-1 text-[11px] font-medium text-mk-app-muted">
          {{ $t('landing.mock.inventory.warehouse') }}
          <ArrowLeft class="size-3 ltr:rotate-180" />
          {{ $t('landing.mock.inventory.van', { number: MOCK_TRANSFER.van }) }}
        </span>
      </div>
    </div>
    <div class="flex items-center justify-between rounded-lg bg-mk-app-bg px-2.5 py-2 text-xs">
      <span class="font-medium text-mk-app-ink">{{ $t(`landing.mock.products.${MOCK_TRANSFER.key}`) }}</span>
      <span class="font-bold text-mk-primary">{{ $t('landing.mock.kg', { qty: MOCK_TRANSFER.qty }) }}</span>
    </div>
  </div>
</template>
