<script setup>
import { Scale } from 'lucide-vue-next';
import { LANDING_INDUSTRIES, industryLabelKey, industryRoute } from '../../../Composables/useLandingNav';

const PILL =
  'inline-flex min-h-11 items-center gap-2 rounded-full border border-mk-line bg-mk-surface px-3.5 text-[15px] font-medium text-mk-ink';
</script>

<template>
  <section aria-labelledby="audience-label" class="border-b border-mk-line bg-mk-surface px-5 py-7 sm:px-6">
    <div class="mx-auto flex max-w-[1200px] flex-col items-center gap-3.5 lg:flex-row lg:justify-center lg:gap-3">
      <h2 id="audience-label" class="text-base font-bold text-mk-muted">{{ $t('landing.home.audience.label') }}</h2>
      <ul class="flex flex-wrap justify-center gap-2 lg:gap-3">
        <li v-for="item in LANDING_INDUSTRIES" :key="item.key">
          <router-link
            :to="industryRoute(item.key)"
            :class="PILL"
            class="transition hover:border-mk-primary focus-visible:outline-2 focus-visible:outline-mk-primary"
          >
            <component :is="item.icon" class="size-4 text-mk-primary" aria-hidden="true" />
            {{ $t(industryLabelKey(item.key)) }}
          </router-link>
        </li>
        <li :class="PILL">
          <Scale class="size-4 text-mk-primary" aria-hidden="true" />
          {{ $t('landing.home.audience.items.weighted.title') }}
        </li>
      </ul>
    </div>
  </section>
</template>
