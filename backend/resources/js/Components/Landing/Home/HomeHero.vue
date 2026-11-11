<script setup>
import { ArrowLeft, Store } from 'lucide-vue-next';
import CheckItem from '../CheckItem.vue';
import MarketingButton from '../MarketingButton.vue';
import MarketingEyebrow from '../MarketingEyebrow.vue';
import MarketingMotif from '../MarketingMotif.vue';
import HeroComposition from '../Mockups/HeroComposition.vue';
import { useLandingAnchors } from '../../../Composables/useLandingAnchors';

const trust = ['languages', 'devices', 'isolated'];
const { sectionHref, goToSection } = useLandingAnchors();
</script>

<template>
  <section aria-labelledby="hero-title" class="relative overflow-hidden bg-mk-hero">
    <div
      class="pointer-events-none absolute -start-[110px] -top-[180px] size-[420px] rounded-full bg-mk-hero-2 opacity-90 blur-[120px] lg:-start-[180px] lg:-top-[260px] lg:size-[700px]"
      aria-hidden="true"
    ></div>
    <MarketingMotif
      class="absolute -end-[170px] -top-[60px] size-[420px] lg:-end-[200px] lg:-top-[120px] lg:size-[760px]"
    />

    <div
      class="relative mx-auto flex max-w-[1248px] flex-col items-center gap-9 px-5 pt-10 pb-12 sm:px-6 lg:flex-row lg:justify-between lg:gap-10 lg:pt-[72px] lg:pb-[88px]"
    >
      <div class="flex w-full flex-col gap-5 lg:max-w-[540px] lg:gap-7">
        <MarketingEyebrow :icon="Store" on-dark>{{ $t('landing.home.hero.eyebrow') }}</MarketingEyebrow>
        <h1
          id="hero-title"
          class="text-[34px] leading-[1.3] font-extrabold text-mk-hero-ink sm:text-[46px] xl:text-[60px] xl:leading-[1.22]"
        >
          {{ $t('landing.home.hero.title') }}
        </h1>
        <p class="text-[17px] leading-[1.65] text-mk-hero-muted lg:text-[21px]">
          {{ $t('landing.home.hero.subtitle') }}
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:gap-3.5">
          <MarketingButton to="/signup" size="lg" :icon="ArrowLeft">{{ $t('landing.request_trial') }}</MarketingButton>
          <MarketingButton
            :href="sectionHref('plans')"
            variant="ghost-dark"
            size="lg"
            @click.prevent="goToSection('plans')"
          >
            {{ $t('landing.home.hero.see_plans') }}
          </MarketingButton>
        </div>
        <ul class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-3">
          <CheckItem v-for="item in trust" :key="item" tone="hero" size="md">
            {{ $t(`landing.home.hero_badges.${item}`) }}
          </CheckItem>
        </ul>
      </div>

      <div class="w-full max-w-[350px] sm:max-w-[520px] lg:me-8 lg:max-w-[630px] lg:flex-1 xl:me-0">
        <HeroComposition />
      </div>
    </div>
  </section>
</template>
