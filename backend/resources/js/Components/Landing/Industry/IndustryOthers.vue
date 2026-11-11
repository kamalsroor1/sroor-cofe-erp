<script setup>
import { computed } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import SectionTitle from '../SectionTitle.vue';
import { LANDING_INDUSTRIES, industryRoute } from '../../../Composables/useLandingNav';
import { industryCopyKey } from './industryPages';

const props = defineProps({
  /** The industry of the current page; it is left out of the list. */
  current: { type: String, required: true },
});

const others = computed(() => LANDING_INDUSTRIES.filter((item) => item.key !== props.current));
</script>

<template>
  <section aria-labelledby="others-title" class="bg-mk-bg px-5 pb-14 sm:px-6 lg:pb-24">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-7">
      <SectionTitle id="others-title" size="md" :title="$t('marketing.industry.others_title')" />
      <ul class="grid gap-2.5 md:grid-cols-3 lg:gap-6">
        <li v-for="item in others" :key="item.key">
          <router-link
            :to="industryRoute(item.key)"
            class="flex min-h-[72px] items-center gap-3 rounded-2xl border border-mk-line bg-mk-surface p-4 transition hover:border-mk-primary focus-visible:outline-2 focus-visible:outline-mk-primary"
          >
            <MarketingIconBadge :icon="item.icon" :size="40" />
            <span class="flex-1 text-base font-bold text-mk-ink">{{ $t(`${industryCopyKey(item.key)}.kicker`) }}</span>
            <ArrowLeft class="size-[18px] shrink-0 text-mk-primary ltr:rotate-180" aria-hidden="true" />
          </router-link>
        </li>
      </ul>
    </div>
  </section>
</template>
