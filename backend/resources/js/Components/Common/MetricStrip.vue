<script setup>
import { computed } from 'vue';

const props = defineProps({
  // Columns once the strip becomes a grid (md and up).
  columns: { type: Number, default: 4, validator: (value) => [2, 3, 4].includes(value) },
});

const gridClass = computed(
  () =>
    ({
      2: 'md:grid-cols-2',
      3: 'md:grid-cols-3',
      4: 'md:grid-cols-2 xl:grid-cols-4',
    })[props.columns]
);
</script>

<template>
  <!-- Phones: one swipeable row (cards keep a readable minimum width). md+: a compact grid. -->
  <div
    class="flex md:grid gap-2.5 md:gap-3 overflow-x-auto md:overflow-visible snap-x snap-mandatory md:snap-none pb-1 md:pb-0 -mx-1 px-1 md:mx-0 md:px-0 [&>*]:shrink-0 [&>*]:basis-[10.5rem] [&>*]:snap-start md:[&>*]:basis-auto md:[&>*]:shrink"
    :class="gridClass"
    data-testid="metric-strip"
  >
    <slot />
  </div>
</template>
