<script setup>
import { computed } from 'vue';
import CheckItem from '../CheckItem.vue';
import MarketingIconBadge from '../MarketingIconBadge.vue';
import { featureGroupKey } from './featureGroups';

const props = defineProps({
  /** A FEATURE_GROUPS entry ({ key, icon, items }). */
  group: { type: Object, required: true },
  /** Bullets in two columns from sm (the wide zig-zag rows); cards keep one column. */
  columns: { type: Boolean, default: false },
  /** Smaller heading for the 2-column cards. */
  compact: { type: Boolean, default: false },
});

const base = computed(() => featureGroupKey(props.group.key));
</script>

<template>
  <div class="flex flex-col gap-4">
    <MarketingIconBadge :icon="group.icon" :size="52" />
    <h2
      class="leading-[1.3] font-bold text-mk-ink"
      :class="compact ? 'text-2xl lg:text-[26px]' : 'text-[26px] lg:text-[34px]'"
    >
      {{ $t(`${base}.title`) }}
    </h2>
    <p class="text-base leading-[1.6] text-mk-muted lg:text-lg">{{ $t(`${base}.body`) }}</p>
    <ul class="grid gap-3" :class="{ 'sm:grid-cols-2 sm:gap-x-8 sm:gap-y-3.5': columns }">
      <CheckItem v-for="item in group.items" :key="item">{{ $t(`${base}.items.${item}`) }}</CheckItem>
    </ul>
  </div>
</template>
