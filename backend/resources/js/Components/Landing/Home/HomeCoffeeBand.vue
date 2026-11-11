<script setup>
import { Coffee } from 'lucide-vue-next';
import MarketingEyebrow from '../MarketingEyebrow.vue';
import MarketingMotif from '../MarketingMotif.vue';
import BlendMockup from '../Mockups/BlendMockup.vue';
</script>

<template>
  <section aria-labelledby="coffee-title" class="bg-mk-bg lg:px-6">
    <div
      class="relative mx-auto flex max-w-[1200px] flex-col items-center gap-8 overflow-hidden bg-mk-band px-5 py-10 lg:flex-row lg:justify-between lg:gap-16 lg:rounded-[36px] lg:px-[72px] lg:py-16"
    >
      <MarketingMotif class="absolute -end-[200px] -top-20 size-[520px] lg:-end-[120px]" />
      <div class="relative flex w-full flex-col gap-5 lg:max-w-[560px]">
        <MarketingEyebrow :icon="Coffee" on-dark>{{ $t('landing.home.coffee.eyebrow') }}</MarketingEyebrow>
        <h2 id="coffee-title" class="text-[28px] leading-[1.3] font-extrabold text-mk-hero-ink lg:text-[40px]">
          {{ $t('landing.home.coffee.title') }}
        </h2>
        <p class="text-[17px] leading-[1.65] text-mk-hero-muted lg:text-[19px]">{{ $t('landing.home.coffee.body') }}</p>
        <!-- Optional extras under the copy (the features page lists the blend bullets here). -->
        <slot />
      </div>
      <BlendMockup class="relative" />
    </div>
  </section>
</template>
