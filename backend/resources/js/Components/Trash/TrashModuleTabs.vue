<template>
  <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto font-tajawal">
    <button
      v-for="t in tabsList"
      :key="t.id"
      type="button"
      @click="$emit('change-tab', t.id)"
      class="min-h-[40px] px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap cursor-pointer active:scale-95 select-none"
      :class="currentTab === t.id ? 'bg-theme-primary text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800'"
    >
      <span>{{ t.icon }}</span>
      <span>{{ t.label }}</span>
      <span
        class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
        :class="currentTab === t.id ? 'bg-white/25 text-white' : (counts[t.id] > 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400')"
      >
        {{ counts[t.id] || 0 }}
      </span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  currentTab: { type: String, default: 'items' },
  tabsList: { type: Array, default: () => [] },
  counts: { type: Object, default: () => ({}) },
});

defineEmits(['change-tab']);
</script>
