<script setup>
import MarketingIconBadge from '../MarketingIconBadge.vue';

defineProps({
  icon: { type: [Object, Function], required: true },
  title: { type: String, required: true },
  /** Phone number / email, shown big in brand green (always LTR). */
  value: { type: String, default: '' },
  body: { type: String, default: '' },
});
</script>

<template>
  <article class="flex h-full flex-col gap-3.5 rounded-3xl border border-mk-line bg-mk-surface p-6 lg:p-7">
    <MarketingIconBadge :icon="icon" :size="52" />
    <h2 class="text-lg font-bold text-mk-ink">{{ title }}</h2>
    <p v-if="value" class="text-xl font-bold break-all text-mk-primary">
      <span dir="ltr">{{ value }}</span>
    </p>
    <p v-if="body" class="text-base leading-[1.6] text-mk-muted">{{ body }}</p>
    <div class="mt-auto flex flex-wrap gap-2.5 pt-1"><slot /></div>
  </article>
</template>
