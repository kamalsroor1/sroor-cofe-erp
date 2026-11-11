<script setup>
import { computed } from 'vue';
import { Boxes, ChartColumn, ScanBarcode } from 'lucide-vue-next';
import MarketingFeatureRow from '../MarketingFeatureRow.vue';
import SectionTitle from '../SectionTitle.vue';
import FeatureStage from '../Mockups/FeatureStage.vue';
import { useTrans } from '../../../Composables/useTrans';

const { t } = useTrans();

const ROWS = [
  { key: 'pos', icon: ScanBarcode, bullets: ['multi_orders', 'payments', 'shifts'] },
  { key: 'inventory', icon: Boxes, bullets: ['transfers', 'units', 'reorder'] },
  { key: 'reports', icon: ChartColumn, bullets: ['profit', 'statements', 'treasury'] },
];

const rows = computed(() =>
  ROWS.map((row) => {
    const base = `landing.home.showcase.rows.${row.key}`;
    return {
      key: row.key,
      icon: row.icon,
      kicker: t(`${base}.kicker`),
      title: t(`${base}.title`),
      body: t(`${base}.body`),
      bullets: row.bullets.map((bullet) => t(`${base}.bullets.${bullet}`)),
    };
  })
);
</script>

<template>
  <section
    id="features"
    aria-labelledby="showcase-title"
    tabindex="-1"
    class="scroll-mt-24 bg-mk-bg px-5 py-16 focus:outline-none sm:px-6 lg:py-28"
  >
    <div class="mx-auto flex max-w-[1200px] flex-col gap-14 lg:gap-28">
      <SectionTitle
        id="showcase-title"
        :title="$t('landing.home.showcase.title')"
        :subtitle="$t('landing.home.showcase.subtitle')"
      />
      <MarketingFeatureRow
        v-for="(row, index) in rows"
        :key="row.key"
        :kicker="row.kicker"
        :icon="row.icon"
        :title="row.title"
        :body="row.body"
        :bullets="row.bullets"
        :reverse="index % 2 === 1"
      >
        <template #visual><FeatureStage :kind="row.key" /></template>
      </MarketingFeatureRow>
    </div>
  </section>
</template>
