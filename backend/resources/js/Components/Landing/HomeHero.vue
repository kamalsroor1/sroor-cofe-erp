<script setup>
import { ArrowLeft, Check } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import HeroVisual from './HeroVisual.vue';
import { useLandingAnchors } from '../../Composables/useLandingAnchors';

defineProps({
  brand: { type: Object, default: () => ({}) },
  isLoading: { type: Boolean, default: false },
});

const badges = ['languages', 'devices', 'isolated'];
const { sectionHref, goToSection } = useLandingAnchors();
</script>

<template>
  <section aria-labelledby="hero-title" class="grid items-center gap-10 py-4 sm:py-10 lg:grid-cols-2 lg:gap-14">
    <div class="space-y-6 text-center lg:text-start">
      <Skeleton v-if="isLoading" width="w-40 mx-auto lg:mx-0" height="h-4" />
      <p v-else-if="brand.name" class="text-xs font-black" style="color: var(--color-primary)">
        {{ brand.name }}<span v-if="brand.subtitle"> - {{ brand.subtitle }}</span>
      </p>
      <h1
        id="hero-title"
        class="text-3xl font-black leading-tight text-slate-900 dark:text-white sm:text-4xl lg:text-5xl lg:leading-tight"
      >
        {{ $t('landing.home.hero_title') }}
      </h1>
      <p class="mx-auto max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400 sm:text-base lg:mx-0">
        {{ $t('landing.home.hero_subtitle') }}
      </p>

      <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:justify-center lg:justify-start">
        <BaseButton
          to="/signup"
          variant="primary"
          size="lg"
          :trailing-icon="ArrowLeft"
          trailing-icon-class="ltr:rotate-180"
        >
          {{ $t('landing.request_trial') }}
        </BaseButton>
        <a
          :href="sectionHref('plans')"
          class="inline-flex min-h-[52px] items-center justify-center rounded-2xl border border-theme-primary px-6 text-sm font-black text-theme-primary transition hover:bg-theme-primary/10 active:scale-[0.98]"
          @click.prevent="goToSection('plans')"
        >
          {{ $t('landing.nav.plans') }}
        </a>
      </div>

      <ul class="flex flex-wrap justify-center gap-2 lg:justify-start">
        <li
          v-for="badge in badges"
          :key="badge"
          class="flex min-h-9 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
        >
          <Check class="h-3.5 w-3.5 shrink-0" style="color: var(--color-primary)" />
          {{ $t(`landing.home.hero_badges.${badge}`) }}
        </li>
      </ul>
    </div>

    <HeroVisual />
  </section>
</template>
