<script setup>
import { Globe } from 'lucide-vue-next';
import MarketingButton from '../Landing/MarketingButton.vue';
import MarketingIconBadge from '../Landing/MarketingIconBadge.vue';
</script>

<template>
  <article class="flex flex-col gap-[18px] rounded-3xl border border-mk-line bg-mk-surface p-6">
    <header class="flex items-start gap-3.5">
      <MarketingIconBadge :icon="Globe" :size="48" />
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <h2 class="text-[19px] font-bold text-mk-ink">{{ $t('marketing.download.browser.title') }}</h2>
        <p class="text-sm leading-[1.55] text-mk-muted">{{ $t('marketing.download.browser.body') }}</p>
      </div>
    </header>
    <MarketingButton :to="{ name: 'login' }" variant="primary" block>
      {{ $t('marketing.download.browser.cta') }}
    </MarketingButton>
  </article>
</template>
