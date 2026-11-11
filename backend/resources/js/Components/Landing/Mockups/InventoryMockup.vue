<script setup>
import { ArrowLeftRight, ChevronDown } from 'lucide-vue-next';
import BrowserFrame from './BrowserFrame.vue';
import MockChip from './MockChip.vue';
import MockSidebar from './MockSidebar.vue';
import { MOCK_STOCK } from './mockData';

defineProps({
  width: { type: Number, default: 620 },
  height: { type: Number, default: 400 },
});

const columns = ['main', 'warehouse', 'van'];
const COLUMN_WIDTH = { main: 'w-[110px]', warehouse: 'w-[100px]', van: 'w-[90px]' };
</script>

<template>
  <BrowserFrame :width="width" :height="height">
    <div class="flex h-full">
      <MockSidebar active="inventory" />

      <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
        <div class="flex items-center gap-2">
          <span class="flex-1 text-[15px] font-extrabold text-mk-app-ink">{{
            $t('landing.mock.inventory.title')
          }}</span>
          <MockChip tone="outline" :height="28">
            {{ $t('landing.mock.inventory.all_branches') }}
            <ChevronDown class="size-3.5" />
          </MockChip>
          <MockChip tone="primary" :height="28">
            <ArrowLeftRight class="size-3.5" />
            {{ $t('landing.mock.inventory.transfer') }}
          </MockChip>
        </div>

        <div class="rounded-xl border border-mk-app-line bg-mk-app-surface p-1.5">
          <div class="flex gap-2 rounded-[10px] bg-mk-app-bg px-3.5 py-2.5 text-xs font-bold text-mk-app-muted">
            <span class="flex-1 text-start">{{ $t('landing.mock.inventory.item') }}</span>
            <span class="text-center" :class="COLUMN_WIDTH.main">{{ $t('landing.mock.inventory.main_branch') }}</span>
            <span class="text-center" :class="COLUMN_WIDTH.warehouse">{{
              $t('landing.mock.inventory.warehouse')
            }}</span>
            <span class="text-center" :class="COLUMN_WIDTH.van">{{
              $t('landing.mock.inventory.van', { number: 1 })
            }}</span>
          </div>
          <div
            v-for="row in MOCK_STOCK"
            :key="row.key"
            class="flex items-center gap-2 border-b border-mk-app-line px-3.5 py-[11px] text-xs font-medium text-mk-app-ink last:border-b-0"
          >
            <span class="flex min-w-0 flex-1 items-center gap-1.5">
              <span class="truncate font-bold">{{ $t(`landing.mock.products.${row.key}`) }}</span>
              <MockChip v-if="row.low" tone="warn" :height="20">{{ $t('landing.mock.inventory.low') }}</MockChip>
            </span>
            <span
              v-for="column in columns"
              :key="column"
              class="text-center"
              :class="[COLUMN_WIDTH[column], { 'text-mk-low': row.low && column === 'main' }]"
              >{{ row[column] }}</span
            >
          </div>
        </div>
      </div>
    </div>
  </BrowserFrame>
</template>
