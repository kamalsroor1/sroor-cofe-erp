<template>
  <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200 dark:border-slate-800 text-xs font-bold font-tajawal">
    <button
      v-for="t in tabs"
      :key="t.key"
      type="button"
      @click="$emit('update:activeTab', t.key)"
      class="min-h-[38px] px-4 py-2 rounded-xl transition flex items-center gap-2 whitespace-nowrap cursor-pointer active:scale-95"
      :class="activeTab === t.key ? 'bg-theme-primary text-white font-black shadow-theme-primary' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100/80 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800'"
    >
      <span>{{ t.icon }}</span>
      <span>{{ t.label }}</span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  tabs: { type: Array, default: () => [] },
  activeTab: { type: String, default: 'sales' },
});

defineEmits(['update:activeTab']);
</script>
