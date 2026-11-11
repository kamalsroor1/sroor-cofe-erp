<script setup>
import { computed } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import MarketingButton from '../MarketingButton.vue';
import MarketingEyebrow from '../MarketingEyebrow.vue';
import MarketingMotif from '../MarketingMotif.vue';
import IndustryHeroVisual from './IndustryHeroVisual.vue';
import { industryCopyKey } from './industryPages';

const props = defineProps({
  industry: { type: String, required: true },
  icon: { type: [Object, Function], required: true },
  /** INDUSTRY_PAGES[industry].visual */
  visual: { type: String, default: 'pos' },
});

const base = computed(() => industryCopyKey(props.industry));
</script>

<template>
  <section aria-labelledby="industry-title" class="relative overflow-hidden bg-mk-hero">
    <MarketingMotif
      class="absolute -end-[170px] -top-[60px] size-[400px] lg:-end-[200px] lg:-top-[120px] lg:size-[700px]"
    />
    <div
      class="relative mx-auto flex max-w-[1248px] flex-col items-center gap-9 px-5 pt-10 pb-12 sm:px-6 lg:flex-row lg:justify-between lg:gap-10 lg:pt-[72px] lg:pb-20"
    >
      <div class="flex w-full flex-col gap-5 lg:max-w-[560px] lg:gap-[26px]">
        <MarketingEyebrow :icon="icon" on-dark>{{ $t(`${base}.kicker`) }}</MarketingEyebrow>
        <h1
          id="industry-title"
          class="text-[32px] leading-[1.25] font-bold text-mk-hero-ink sm:text-[44px] xl:text-[54px]"
        >
          {{ $t(`${base}.title`) }}
        </h1>
        <p class="text-[17px] leading-[1.65] text-mk-hero-muted lg:text-xl">{{ $t(`${base}.subtitle`) }}</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:gap-3.5">
          <MarketingButton to="/signup" size="lg" :icon="ArrowLeft">{{ $t('landing.request_trial') }}</MarketingButton>
          <MarketingButton :to="{ name: 'landing.pricing' }" variant="ghost-dark" size="lg">
            {{ $t('marketing.features.see_pricing') }}
          </MarketingButton>
        </div>
      </div>
      <div class="w-full lg:flex-1" :class="visual === 'blend' ? 'max-w-[400px] lg:max-w-[540px]' : 'max-w-[560px]'">
        <IndustryHeroVisual :kind="visual" />
      </div>
    </div>
  </section>
</template>
