<script setup>
import { computed } from 'vue';
import CheckItem from '../CheckItem.vue';
import HomeCoffeeBand from '../Home/HomeCoffeeBand.vue';
import { featureGroupId, featureGroupKey } from './featureGroups';

const props = defineProps({
  /** The FEATURE_GROUPS `band` entry (coffee blends). */
  group: { type: Object, required: true },
});

const base = computed(() => featureGroupKey(props.group.key));
</script>

<template>
  <!-- The homepage coffee band, with the blend group's bullets: the chip bar target for this group. -->
  <div :id="featureGroupId(group.key)" tabindex="-1" class="scroll-mt-[150px] focus:outline-none">
    <HomeCoffeeBand>
      <ul class="flex flex-col gap-3">
        <CheckItem v-for="item in group.items" :key="item" tone="hero">{{ $t(`${base}.items.${item}`) }}</CheckItem>
      </ul>
    </HomeCoffeeBand>
  </div>
</template>
