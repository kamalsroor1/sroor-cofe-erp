<template>
  <div class="space-y-4 font-tajawal">
    <div class="flex flex-wrap gap-2.5">
      <div
        v-for="(u, idx) in units"
        :key="u"
        class="ps-4 pe-1 min-h-11 rounded-xl text-xs font-bold bg-purple-500/10 dark:bg-purple-950/40 border border-purple-500/30 text-purple-700 dark:text-purple-300 flex items-center gap-1 shadow-2xs"
      >
        <span class="text-sm font-black">{{ u }}</span>
        <button
          type="button"
          @click="$emit('remove', idx)"
          class="w-11 h-11 rounded-full text-slate-500 dark:text-slate-400 hover:bg-rose-500/20 hover:text-rose-500 focus-visible:ring-2 focus-visible:ring-rose-500/50 flex items-center justify-center transition cursor-pointer active:scale-95"
          :title="$t('common.delete')"
          :aria-label="$t('common.delete')"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
// SETG-10: a unit is just a name from the platform catalog. Whether an item is sold by
// weight is the item's own flag (POSB-2), so no name-based "discrete / continuous" badge.
import { X } from 'lucide-vue-next';

defineProps({
  units: { type: Array, default: () => [] },
});

defineEmits(['remove']);
</script>
