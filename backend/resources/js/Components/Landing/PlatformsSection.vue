<script setup>
import { Download, Globe, Monitor, Smartphone } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import LandingSection from './LandingSection.vue';

const platforms = [
  { key: 'web', icon: Globe },
  { key: 'android', icon: Smartphone },
  { key: 'windows', icon: Monitor },
];
</script>

<template>
  <LandingSection
    id="platforms"
    :title="$t('landing.home.platforms.title')"
    :subtitle="$t('landing.home.platforms.subtitle')"
  >
    <ul class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <li
        v-for="platform in platforms"
        :key="platform.key"
        class="flex items-start gap-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <span
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
          style="background: var(--color-primary-light); color: var(--color-primary)"
        >
          <component :is="platform.icon" class="h-6 w-6" />
        </span>
        <div class="space-y-1">
          <h3 class="text-sm font-black text-slate-900 dark:text-white">
            {{ $t(`landing.home.platforms.items.${platform.key}.title`) }}
          </h3>
          <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {{ $t(`landing.home.platforms.items.${platform.key}.body`) }}
          </p>
        </div>
      </li>
    </ul>
    <div class="flex justify-center">
      <BaseButton :to="{ name: 'app.download' }" variant="outline" size="md" :icon="Download">
        {{ $t('landing.home.platforms.download_cta') }}
      </BaseButton>
    </div>
  </LandingSection>
</template>
