<script setup>
import MarketingEyebrow from './MarketingEyebrow.vue';
import MarketingMotif from './MarketingMotif.vue';

defineProps({
  kicker: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>

<template>
  <header class="relative overflow-hidden bg-mk-hero px-5 pt-12 pb-14 sm:px-6 lg:pt-20 lg:pb-[88px]">
    <MarketingMotif class="absolute -end-[170px] -top-[90px] size-[360px] lg:-end-40 lg:-top-[140px] lg:size-[560px]" />
    <div class="relative mx-auto flex max-w-[820px] flex-col items-center gap-[18px] text-center">
      <MarketingEyebrow v-if="kicker" :icon="icon" on-dark>{{ kicker }}</MarketingEyebrow>
      <h1 class="text-[32px] leading-[1.28] font-extrabold text-mk-hero-ink lg:text-[52px]">{{ title }}</h1>
      <p v-if="subtitle" class="text-[17px] leading-[1.6] text-mk-hero-muted lg:text-xl">{{ subtitle }}</p>
      <slot />
    </div>
  </header>
</template>
