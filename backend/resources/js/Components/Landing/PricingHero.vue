<script setup>
import Skeleton from '../Common/Skeletons/Skeleton.vue';
import BillingToggle from './BillingToggle.vue';

defineProps({
  brand: { type: Object, default: () => ({}) },
  billing: { type: String, default: 'monthly' },
  isLoading: { type: Boolean, default: false },
});

defineEmits(['update:billing']);
</script>

<template>
  <section class="space-y-4 text-center">
    <div v-if="isLoading" class="mx-auto max-w-md space-y-3">
      <Skeleton width="w-2/3 mx-auto" height="h-8" />
      <Skeleton height="h-4" />
    </div>
    <template v-else>
      <p v-if="brand.name" class="text-xs font-black" style="color: var(--color-primary)">
        {{ brand.name }}<span v-if="brand.subtitle"> - {{ brand.subtitle }}</span>
      </p>
      <h1 class="text-2xl font-black text-slate-900 dark:text-white sm:text-3xl">{{ $t('landing.pricing.title') }}</h1>
      <p class="mx-auto max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
        {{ $t('landing.pricing.subtitle') }}
      </p>
    </template>
    <BillingToggle :model-value="billing" @update:model-value="$emit('update:billing', $event)" />
  </section>
</template>
