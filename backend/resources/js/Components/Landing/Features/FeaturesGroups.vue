<script setup>
import { computed } from 'vue';
import FeatureStage from '../Mockups/FeatureStage.vue';
import FeatureGroupText from './FeatureGroupText.vue';
import { featureGroupId } from './featureGroups';

const props = defineProps({
  /** FEATURE_GROUPS; `row` groups get a zig-zag row with their mockup, `card` groups share a 2-column grid. */
  groups: { type: Array, required: true },
});

/* Page order (build_pages.py features_page): two rows, the card grid, then the remaining row(s). */
const rows = computed(() => props.groups.filter((group) => group.layout === 'row'));
const cards = computed(() => props.groups.filter((group) => group.layout === 'card'));
const blocks = computed(() => [
  ...rows.value.slice(0, 2).map((group, index) => ({ type: 'row', group, reverse: index % 2 === 1 })),
  { type: 'grid', key: 'cards' },
  ...rows.value.slice(2).map((group, index) => ({ type: 'row', group, reverse: index % 2 === 1 })),
]);

// Sections are focused after a chip jump; the scroll margin clears the sticky nav + chip bar.
const SECTION = 'scroll-mt-[150px] focus:outline-none';
</script>

<template>
  <div class="bg-mk-bg px-5 pt-12 pb-16 sm:px-6 lg:py-24">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-12 lg:gap-24">
      <template v-for="block in blocks" :key="block.type === 'row' ? block.group.key : block.key">
        <section
          v-if="block.type === 'row'"
          :id="featureGroupId(block.group.key)"
          tabindex="-1"
          class="flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between lg:gap-14"
          :class="[SECTION, { 'lg:flex-row-reverse': block.reverse }]"
        >
          <div class="lg:w-[520px] lg:shrink-0">
            <FeatureGroupText :group="block.group" columns />
          </div>
          <div class="w-full lg:max-w-[600px] lg:flex-1">
            <FeatureStage :kind="block.group.key" />
          </div>
        </section>

        <div v-else class="grid gap-4 md:grid-cols-2">
          <section
            v-for="group in cards"
            :id="featureGroupId(group.key)"
            :key="group.key"
            tabindex="-1"
            class="rounded-3xl border border-mk-line bg-mk-surface p-6 lg:p-8"
            :class="SECTION"
          >
            <FeatureGroupText :group="group" compact />
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
