<script setup>
import { ArrowLeft } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
</script>

<template>
  <section
    aria-labelledby="final-cta-title"
    class="space-y-4 rounded-3xl border p-8 text-center sm:p-10"
    style="background: var(--color-primary-light); border-color: var(--color-primary-border)"
  >
    <h2 id="final-cta-title" class="text-2xl font-black text-slate-900 dark:text-white">
      {{ $t('landing.home.cta_title') }}
    </h2>
    <p class="mx-auto max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
      {{ $t('landing.home.cta_subtitle') }}
    </p>
    <BaseButton
      to="/signup"
      variant="primary"
      size="lg"
      :trailing-icon="ArrowLeft"
      trailing-icon-class="ltr:rotate-180"
    >
      {{ $t('landing.request_trial') }}
    </BaseButton>
  </section>
</template>
