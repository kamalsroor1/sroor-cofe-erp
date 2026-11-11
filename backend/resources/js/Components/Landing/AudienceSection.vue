<script setup>
import { Coffee, Scale, ShoppingBasket, Truck, Warehouse } from 'lucide-vue-next';
import LandingSection from './LandingSection.vue';

const audiences = [
  { key: 'coffee', icon: Coffee },
  { key: 'retail', icon: ShoppingBasket },
  { key: 'wholesale', icon: Warehouse },
  { key: 'vans', icon: Truck },
  { key: 'weighted', icon: Scale },
];
</script>

<template>
  <LandingSection
    id="audience"
    :title="$t('landing.home.audience.title')"
    :subtitle="$t('landing.home.audience.subtitle')"
  >
    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
      <li
        v-for="audience in audiences"
        :key="audience.key"
        class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <span
          class="flex h-11 w-11 items-center justify-center rounded-2xl"
          style="background: var(--color-primary-light); color: var(--color-primary)"
        >
          <component :is="audience.icon" class="h-5 w-5" />
        </span>
        <h3 class="text-sm font-black text-slate-900 dark:text-white">
          {{ $t(`landing.home.audience.items.${audience.key}.title`) }}
        </h3>
        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {{ $t(`landing.home.audience.items.${audience.key}.body`) }}
        </p>
      </li>
    </ul>
  </LandingSection>
</template>
