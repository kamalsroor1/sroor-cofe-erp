<template>
  <div class="space-y-4 font-tajawal">
    <div class="flex flex-wrap gap-2.5">
      <div
        v-for="(u, idx) in units"
        :key="u"
        class="px-4 py-2 rounded-xl text-xs font-bold bg-purple-500/10 dark:bg-purple-950/40 border border-purple-500/30 text-purple-700 dark:text-purple-300 flex items-center gap-2.5 shadow-2xs group"
      >
        <span class="text-sm font-black">{{ u }}</span>
        <span
          class="px-1.5 py-0.5 rounded text-[10px] font-bold"
          :class="isDiscrete(u) ? 'bg-theme-light text-theme-primary' : 'bg-blue-500/20 text-blue-700 dark:text-blue-300'"
        >
          {{ isDiscrete(u) ? $t('super.discrete_unit_badge') : $t('super.continuous_unit_badge') }}
        </span>
        <button
          type="button"
          @click="$emit('remove', idx)"
          class="w-5 h-5 rounded-full hover:bg-rose-500/20 hover:text-rose-500 flex items-center justify-center text-xs transition cursor-pointer text-slate-400 active:scale-95"
          :title="$t('common.delete')"
        >
          ✕
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  units: { type: Array, default: () => [] },
});

defineEmits(['remove']);

const isDiscrete = (unit) => {
    if (!unit) return true;
    const u = unit.toString().trim().toLowerCase();
    const discrete = ['قطعة', 'حبة', 'علبة', 'باكت', 'كرتونة', 'شيكارة', 'طرد', 'دستة', 'جوال', 'طقم', 'زوج', 'باليتة', 'صندوق', 'برميل', 'شريحة', 'piece', 'pcs', 'box', 'carton', 'pack', 'unit', 'item'];
    return discrete.includes(u);
};
</script>
