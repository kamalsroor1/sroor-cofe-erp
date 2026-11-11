<script setup>
import CheckItem from './CheckItem.vue';
import MarketingEyebrow from './MarketingEyebrow.vue';

defineProps({
  kicker: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
  title: { type: String, required: true },
  body: { type: String, default: '' },
  bullets: { type: Array, default: () => [] },
  /** Put the visual first (zig-zag rows); only affects the desktop row layout. */
  reverse: { type: Boolean, default: false },
  headingTag: { type: String, default: 'h3' },
});
</script>

<template>
  <article
    class="flex flex-col gap-7 lg:items-center lg:justify-between lg:gap-16"
    :class="reverse ? 'lg:flex-row-reverse' : 'lg:flex-row'"
  >
    <div class="flex flex-col gap-[18px] lg:w-[480px] lg:shrink-0 lg:gap-[22px]">
      <MarketingEyebrow v-if="kicker" :icon="icon">{{ kicker }}</MarketingEyebrow>
      <component :is="headingTag" class="text-[28px] leading-[1.3] font-extrabold text-mk-ink lg:text-[40px]">
        {{ title }}
      </component>
      <p v-if="body" class="text-[17px] leading-[1.65] text-mk-muted lg:text-[19px]">{{ body }}</p>
      <ul v-if="bullets.length" class="flex flex-col gap-3.5">
        <CheckItem v-for="bullet in bullets" :key="bullet" size="lg">{{ bullet }}</CheckItem>
      </ul>
    </div>
    <div class="w-full lg:max-w-[640px] lg:flex-1">
      <slot name="visual" />
    </div>
  </article>
</template>
