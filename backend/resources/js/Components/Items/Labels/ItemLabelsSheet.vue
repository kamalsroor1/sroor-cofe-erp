<template>
  <div class="item-labels-sheet flex flex-col items-center gap-6 print:gap-0" data-testid="labels-sheet">
    <section
      v-for="(page, pageIdx) in pages"
      :key="pageIdx"
      class="item-labels-page bg-white shadow-xl ring-1 ring-slate-300 dark:ring-slate-700 print:shadow-none print:ring-0"
      :class="{ 'item-labels-page--last': pageIdx === pages.length - 1 }"
      :style="pageStyle"
      data-testid="labels-page"
    >
      <ItemLabelCard
        v-for="label in page"
        :key="label.key"
        :label="label"
        :settings="settings"
        :class="isSheet ? 'item-label--sheet' : ''"
      />
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ItemLabelCard from './ItemLabelCard.vue';

const props = defineProps({
  pages: { type: Array, default: () => [] },
  settings: { type: Object, required: true },
  geometry: { type: Object, required: true },
});

const isSheet = computed(() => props.geometry.preset.kind === 'sheet');

const pageStyle = computed(() => {
  const { width, height, padX, padY, preset } = props.geometry;
  const base = {
    width: `${width}mm`,
    height: `${height}mm`,
    padding: `${padY.toFixed(2)}mm ${padX.toFixed(2)}mm`,
  };
  if (!isSheet.value) return base;
  return {
    ...base,
    display: 'grid',
    gridTemplateColumns: `repeat(${preset.cols}, ${preset.width}mm)`,
    gridAutoRows: `${preset.height}mm`,
    alignContent: 'start',
  };
});
</script>

<style scoped>
.item-labels-page {
  box-sizing: border-box;
  overflow: hidden;
}

@media print {
  .item-labels-page {
    break-after: page;
    page-break-after: always;
  }

  .item-labels-page--last {
    break-after: auto;
    page-break-after: auto;
  }
}

/* Faint cut guides on screen only for A4 sheets. */
@media screen {
  .item-label--sheet {
    outline: 1px dashed rgb(203 213 225);
    outline-offset: -1px;
  }
}
</style>
