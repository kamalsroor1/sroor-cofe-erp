<script setup>
import MarketingIconBadge from '../MarketingIconBadge.vue';
import SectionTitle from '../SectionTitle.vue';

defineProps({
  industry: { type: String, required: true },
  /** INDUSTRY_PAGES[industry].items: [{ key, icon }] */
  items: { type: Array, required: true },
});
</script>

<template>
  <section aria-labelledby="daily-title" class="bg-mk-bg px-5 pt-14 pb-4 sm:px-6 lg:pt-24 lg:pb-8">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-8">
      <SectionTitle id="daily-title" size="md" :title="$t('marketing.industry.pains_title')" />
      <ul class="grid gap-3 md:grid-cols-3 lg:gap-6">
        <li
          v-for="item in items"
          :key="item.key"
          class="flex flex-col gap-3 rounded-3xl border border-mk-line bg-mk-surface p-6 lg:p-7"
        >
          <MarketingIconBadge :icon="item.icon" :size="52" />
          <h3 class="text-xl font-bold text-mk-ink">
            {{ $t(`marketing.industry.items.${industry}.${item.key}.title`) }}
          </h3>
          <p class="text-base leading-[1.6] text-mk-muted">
            {{ $t(`marketing.industry.items.${industry}.${item.key}.body`) }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
