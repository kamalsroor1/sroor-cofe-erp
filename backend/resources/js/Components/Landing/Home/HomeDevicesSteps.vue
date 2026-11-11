<script setup>
import { Globe, Monitor, Smartphone } from 'lucide-vue-next';
import MarketingIconBadge from '../MarketingIconBadge.vue';

const devices = [
  { key: 'web', icon: Globe },
  { key: 'android', icon: Smartphone },
  { key: 'windows', icon: Monitor },
];
const steps = ['request', 'setup', 'start'];
</script>

<template>
  <section class="border-y border-mk-line bg-mk-surface px-5 py-16 sm:px-6 lg:py-24">
    <div
      class="mx-auto flex max-w-[1200px] flex-col-reverse gap-12 lg:flex-row lg:items-start lg:justify-between lg:gap-20"
    >
      <div aria-labelledby="steps-title" role="group" class="flex flex-col gap-[22px] lg:w-[440px] lg:shrink-0">
        <h2 id="steps-title" class="text-[26px] font-extrabold text-mk-ink lg:text-[34px]">
          {{ $t('landing.home.steps.heading') }}
        </h2>
        <ol class="flex flex-col gap-[22px]">
          <li v-for="(step, index) in steps" :key="step" class="flex items-start gap-4">
            <span
              class="flex size-10 shrink-0 items-center justify-center rounded-full bg-mk-accent text-lg font-extrabold text-mk-accent-ink"
              aria-hidden="true"
              >{{ index + 1 }}</span
            >
            <div class="flex flex-col gap-1">
              <h3 class="text-lg font-extrabold text-mk-ink">{{ $t(`landing.home.steps.items.${step}.title`) }}</h3>
              <p class="text-[15px] text-mk-muted">{{ $t(`landing.home.steps.items.${step}.short`) }}</p>
            </div>
          </li>
        </ol>
      </div>

      <div aria-labelledby="devices-title" role="group" class="flex flex-col gap-4 lg:w-[640px]">
        <h2 id="devices-title" class="text-[26px] font-extrabold text-mk-ink lg:text-[34px]">
          {{ $t('landing.home.platforms.title') }}
        </h2>
        <p class="text-[17px] text-mk-muted">{{ $t('landing.home.platforms.subtitle') }}</p>
        <ul class="grid gap-2.5 sm:grid-cols-3 sm:gap-3">
          <li
            v-for="device in devices"
            :key="device.key"
            class="flex items-center gap-3.5 rounded-2xl border border-mk-line bg-mk-surface p-3.5 sm:flex-col sm:items-start sm:gap-2.5 sm:rounded-[18px] sm:p-[18px]"
          >
            <MarketingIconBadge :icon="device.icon" :size="46" />
            <div class="flex flex-col gap-0.5 sm:gap-2.5">
              <span class="text-[17px] font-extrabold text-mk-ink">{{
                $t(`landing.home.devices.${device.key}.title`)
              }}</span>
              <span class="text-sm text-mk-muted">{{ $t(`landing.home.devices.${device.key}.body`) }}</span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
