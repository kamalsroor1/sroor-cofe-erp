<script setup>
import { nextTick, ref, watch } from 'vue';
import { featureGroupId, featureGroupKey } from './featureGroups';

const props = defineProps({
  /** FEATURE_GROUPS entries ({ key, icon }). */
  groups: { type: Array, required: true },
  activeKey: { type: String, default: '' },
});

defineEmits(['select']);

const track = ref(null);

// Keep the active chip visible in the horizontally scrolling bar (mobile). scrollBy works the same in RTL and LTR.
watch(
  () => props.activeKey,
  async (key) => {
    await nextTick();
    const scroller = track.value;
    const chip = scroller?.querySelector(`[data-chip="${key}"]`);
    if (!chip || scroller.scrollWidth <= scroller.clientWidth) return;
    const box = scroller.getBoundingClientRect();
    const rect = chip.getBoundingClientRect();
    const delta = rect.left + rect.width / 2 - (box.left + box.width / 2);
    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    scroller.scrollBy({ left: delta, behavior: reduced ? 'auto' : 'smooth' });
  }
);
</script>

<template>
  <nav
    :aria-label="$t('marketing.features.sections_label')"
    class="sticky top-[68px] z-20 border-b border-mk-line bg-mk-surface/95 backdrop-blur-md lg:top-[76px]"
  >
    <div ref="track" class="overflow-x-auto mk-no-scrollbar">
      <!-- w-max + mx-auto: centred when the chips fit, scrollable from the start edge when they don't. -->
      <ul class="mx-auto flex w-max gap-2 px-5 py-2.5 sm:px-6">
        <li v-for="group in groups" :key="group.key">
          <a
            :href="`#${featureGroupId(group.key)}`"
            :data-chip="group.key"
            :aria-current="group.key === activeKey ? 'true' : undefined"
            class="inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold whitespace-nowrap transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mk-primary"
            :class="
              group.key === activeKey
                ? 'border-mk-primary bg-mk-primary text-mk-primary-ink'
                : 'border-mk-line bg-mk-surface text-mk-ink hover:bg-mk-surface-2'
            "
            @click.prevent="$emit('select', group.key)"
          >
            <component :is="group.icon" class="size-4 shrink-0" aria-hidden="true" />
            {{ $t(`${featureGroupKey(group.key)}.title`) }}
          </a>
        </li>
      </ul>
    </div>
  </nav>
</template>
