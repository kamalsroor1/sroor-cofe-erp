<script setup>
import { ArrowLeft } from 'lucide-vue-next';
import MarketingButton from './MarketingButton.vue';
import MarketingMotif from './MarketingMotif.vue';
</script>

<template>
  <section aria-labelledby="final-cta-title" class="bg-mk-bg px-5 pb-16 sm:px-6 lg:pb-28">
    <div
      class="relative mx-auto flex max-w-[1200px] flex-col items-center gap-5 overflow-hidden rounded-[28px] px-6 py-12 text-center mk-cta-gradient lg:rounded-[36px] lg:p-[72px]"
    >
      <MarketingMotif
        strength="soft"
        class="absolute -end-[120px] -top-[60px] size-[360px] lg:-end-20 lg:-top-[120px] lg:size-[520px]"
      />
      <h2 id="final-cta-title" class="relative text-[34px] font-bold text-white lg:text-[52px]">
        {{ $t('landing.home.cta_title') }}
      </h2>
      <p class="relative text-[17px] text-mk-cta-muted lg:text-[21px]">{{ $t('landing.home.cta_subtitle') }}</p>
      <div class="relative flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:gap-3.5">
        <MarketingButton to="/signup" size="lg" :icon="ArrowLeft">{{ $t('landing.request_trial') }}</MarketingButton>
        <MarketingButton :to="{ name: 'login' }" variant="ghost-dark" size="lg">
          {{ $t('landing.nav.login') }}
        </MarketingButton>
      </div>
    </div>
  </section>
</template>
