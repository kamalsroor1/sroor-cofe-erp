<script setup>
import { ArrowLeft } from 'lucide-vue-next';
import BaseButton from '../Common/BaseButton.vue';
import Skeleton from '../Common/Skeletons/Skeleton.vue';

defineProps({
  brand: { type: Object, default: () => ({}) },
  isLoading: { type: Boolean, default: false },
});
</script>

<template>
  <section class="space-y-5 py-4 text-center sm:py-10">
    <Skeleton v-if="isLoading" width="w-40 mx-auto" height="h-4" />
    <p v-else-if="brand.name" class="text-xs font-black" style="color: var(--color-primary)">
      {{ brand.name }}<span v-if="brand.subtitle"> - {{ brand.subtitle }}</span>
    </p>
    <h1 class="mx-auto max-w-2xl text-3xl font-black leading-tight text-slate-900 dark:text-white sm:text-4xl">
      {{ $t('landing.home.hero_title') }}
    </h1>
    <p class="mx-auto max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400 sm:text-base">
      {{ $t('landing.home.hero_subtitle') }}
    </p>
    <div class="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
      <BaseButton to="/signup" variant="primary" size="lg" :trailing-icon="ArrowLeft">
        {{ $t('landing.request_trial') }}
      </BaseButton>
      <BaseButton to="/pricing" variant="outline" size="lg">{{ $t('landing.nav.pricing') }}</BaseButton>
    </div>
  </section>
</template>
