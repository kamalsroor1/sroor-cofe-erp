<script setup>
import { Bell } from 'lucide-vue-next';
import MockBars from './MockBars.vue';
import MockChip from './MockChip.vue';
import PhoneFrame from './PhoneFrame.vue';
import { MOCK_PHONE } from './mockData';
</script>

<template>
  <PhoneFrame>
    <div class="flex flex-col gap-2.5 px-3.5 pt-[18px] pb-3.5">
      <div class="flex items-center justify-between">
        <span class="text-[13px] font-extrabold text-mk-app-ink">{{ $t('landing.mock.phone.title') }}</span>
        <Bell class="size-4 text-mk-app-muted" />
      </div>

      <div class="flex flex-col items-start gap-1">
        <span class="text-[11px] font-medium text-mk-app-muted">{{ $t('landing.mock.phone.today_sales') }}</span>
        <span class="text-2xl font-extrabold text-mk-app-ink">{{ MOCK_PHONE.sales }}</span>
        <MockChip tone="soft" :height="22">{{
          $t('landing.mock.phone.vs_yesterday', { value: MOCK_PHONE.growth })
        }}</MockChip>
      </div>

      <div class="rounded-xl bg-mk-app-surface px-1.5 pt-2.5 pb-1.5">
        <MockBars :values="MOCK_PHONE.week" :highlight="MOCK_PHONE.week.length - 1" :height="70" :gap="6" />
      </div>

      <div class="grid grid-cols-2 gap-2 rounded-xl bg-mk-app-surface p-2.5">
        <div class="flex flex-col gap-0.5">
          <span class="text-[10px] font-medium text-mk-app-muted">{{ $t('landing.mock.reports.net_profit') }}</span>
          <span class="text-[15px] font-extrabold text-mk-primary">{{ MOCK_PHONE.profit }}</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-[10px] font-medium text-mk-app-muted">{{ $t('landing.mock.phone.treasury') }}</span>
          <span class="text-[15px] font-extrabold text-mk-app-ink">{{ MOCK_PHONE.treasury }}</span>
        </div>
      </div>

      <span class="text-[11px] font-bold text-mk-app-muted">{{ $t('landing.mock.phone.best_sellers') }}</span>
      <div>
        <div
          v-for="row in MOCK_PHONE.best"
          :key="row.key"
          class="flex items-center justify-between border-b border-mk-app-line py-1.5 text-[11px] text-mk-app-ink"
        >
          <span class="font-medium">{{ $t(`landing.mock.products.${row.key}`) }}</span>
          <span class="font-bold">{{ row.value }}</span>
        </div>
      </div>
    </div>
  </PhoneFrame>
</template>
