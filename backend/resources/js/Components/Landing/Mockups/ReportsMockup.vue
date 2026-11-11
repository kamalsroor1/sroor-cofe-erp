<script setup>
import { ArrowDown, ArrowUp } from 'lucide-vue-next';
import BrowserFrame from './BrowserFrame.vue';
import MockBars from './MockBars.vue';
import MockChip from './MockChip.vue';
import MockSidebar from './MockSidebar.vue';
import { MOCK_ITEM_MARGINS, MOCK_REPORT_KPIS, MOCK_REPORT_MONTHS, MOCK_REPORT_YEAR } from './mockData';

defineProps({
  width: { type: Number, default: 620 },
  height: { type: Number, default: 470 },
});
</script>

<template>
  <BrowserFrame :width="width" :height="height">
    <div class="flex h-full">
      <MockSidebar active="reports" />

      <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
        <span class="text-[15px] font-extrabold text-mk-app-ink">{{ $t('landing.mock.reports.title') }}</span>

        <div class="grid grid-cols-3 gap-2.5">
          <div
            v-for="kpi in MOCK_REPORT_KPIS"
            :key="kpi.key"
            class="flex flex-col gap-[3px] rounded-xl border border-mk-app-line bg-mk-app-surface p-3"
          >
            <span class="text-[11px] font-medium text-mk-app-muted">{{ $t(`landing.mock.reports.${kpi.key}`) }}</span>
            <span class="text-lg font-extrabold text-mk-app-ink">{{ kpi.value }}</span>
            <span
              class="flex items-center gap-0.5 text-[10px] font-bold"
              :class="kpi.up ? 'text-mk-up' : 'text-mk-down'"
            >
              <ArrowUp v-if="kpi.up" class="size-2.5" :stroke-width="3" />
              <ArrowDown v-else class="size-2.5" :stroke-width="3" />
              {{ $t('landing.mock.percent', { value: kpi.delta }) }}
            </span>
          </div>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-mk-app-line bg-mk-app-surface p-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-mk-app-ink">{{ $t('landing.mock.reports.pnl') }}</span>
            <MockChip tone="muted" :height="22">{{ MOCK_REPORT_YEAR }}</MockChip>
          </div>
          <MockBars :values="MOCK_REPORT_MONTHS" :height="110" :gap="8" />
        </div>

        <div class="flex flex-col gap-2.5 rounded-xl border border-mk-app-line bg-mk-app-surface p-3">
          <span class="text-xs font-bold text-mk-app-ink">{{ $t('landing.mock.reports.items_profit') }}</span>
          <div v-for="row in MOCK_ITEM_MARGINS" :key="row.key" class="flex items-center gap-2.5 text-[11px]">
            <span class="flex-1 font-medium text-mk-app-ink">{{ $t(`landing.mock.products.${row.key}`) }}</span>
            <span class="flex h-1.5 w-[140px] overflow-hidden rounded-[3px] bg-mk-app-line">
              <span class="rounded-[3px] bg-mk-primary" :style="{ width: `${row.margin}%` }"></span>
            </span>
            <span class="font-bold text-mk-app-ink">{{ $t('landing.mock.percent', { value: row.margin }) }}</span>
          </div>
        </div>
      </div>
    </div>
  </BrowserFrame>
</template>
