<script setup>
import { Coffee, Plus, ScanBarcode, Search } from 'lucide-vue-next';
import BrowserFrame from './BrowserFrame.vue';
import MockChip from './MockChip.vue';
import MockSidebar from './MockSidebar.vue';
import { MOCK_CART, MOCK_CART_TOTAL, MOCK_PRODUCTS } from './mockData';

defineProps({
  width: { type: Number, default: 700 },
  height: { type: Number, default: 470 },
});

const categories = ['coffee', 'drinks', 'sweets'];
</script>

<template>
  <BrowserFrame :width="width" :height="height">
    <div class="flex h-full">
      <MockSidebar active="pos" />

      <div class="flex min-w-0 flex-1 flex-col gap-2.5 p-4">
        <div
          class="flex h-9 items-center gap-2 rounded-[10px] border border-mk-app-line bg-mk-app-surface px-3 text-xs text-mk-app-muted"
        >
          <Search class="size-4 shrink-0" />
          <span class="flex-1 truncate">{{ $t('landing.mock.pos.search') }}</span>
          <ScanBarcode class="size-4 shrink-0 text-mk-primary" />
        </div>
        <div class="flex gap-1.5">
          <MockChip tone="primary">{{ $t('landing.mock.pos.all') }}</MockChip>
          <MockChip v-for="category in categories" :key="category" tone="surface">
            {{ $t(`landing.mock.pos.${category}`) }}
          </MockChip>
        </div>
        <div class="grid grid-cols-3 gap-2.5">
          <div
            v-for="product in MOCK_PRODUCTS"
            :key="product.key"
            class="flex flex-col gap-1.5 rounded-xl border border-mk-app-line bg-mk-app-surface p-2"
          >
            <span
              class="flex h-[46px] items-center justify-center rounded-[10px] text-white/80"
              :style="{ backgroundColor: `${product.color}33` }"
            >
              <Coffee class="size-[22px]" />
            </span>
            <span class="truncate text-xs font-bold text-mk-app-ink">{{
              $t(`landing.mock.products.${product.key}`)
            }}</span>
            <span class="text-[11px] font-medium text-mk-primary">{{
              $t('landing.mock.money', { amount: product.price })
            }}</span>
          </div>
        </div>
      </div>

      <div class="flex w-[230px] shrink-0 flex-col gap-2 border-s border-mk-app-line bg-mk-app-surface p-3.5">
        <div class="flex items-center gap-1.5">
          <MockChip tone="soft">{{ $t('landing.mock.pos.order', { number: 1 }) }}</MockChip>
          <MockChip tone="muted">{{ $t('landing.mock.pos.order', { number: 2 }) }}</MockChip>
          <span class="flex size-[26px] items-center justify-center rounded-full bg-mk-app-bg text-mk-app-muted">
            <Plus class="size-3.5" />
          </span>
        </div>
        <div v-for="line in MOCK_CART" :key="line.key" class="flex items-center gap-2 border-b border-mk-app-line py-2">
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="truncate text-xs font-bold text-mk-app-ink">{{
              $t(`landing.mock.products.${line.key}`)
            }}</span>
            <span class="text-[11px] text-mk-app-muted">{{
              $t(line.weighted ? 'landing.mock.qty_kg' : 'landing.mock.qty_unit', { qty: line.qty, price: line.price })
            }}</span>
          </div>
          <span class="text-xs font-bold text-mk-app-ink">{{ line.total }}</span>
        </div>
        <span class="flex-1"></span>
        <div class="flex gap-1.5">
          <MockChip tone="soft">{{ $t('landing.mock.pos.cash') }}</MockChip>
          <MockChip tone="muted">{{ $t('landing.mock.pos.instapay') }}</MockChip>
          <MockChip tone="muted">{{ $t('landing.mock.pos.card') }}</MockChip>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-[13px] font-medium text-mk-app-muted">{{ $t('landing.mock.pos.total') }}</span>
          <span class="text-lg font-extrabold text-mk-app-ink">{{ MOCK_CART_TOTAL }}</span>
        </div>
        <span
          class="flex h-10 items-center justify-center rounded-[10px] bg-mk-app-cta text-[13px] font-bold text-white"
        >
          {{ $t('landing.mock.pos.pay_print') }}
        </span>
      </div>
    </div>
  </BrowserFrame>
</template>
