<template>
  <nav
    class="flex flex-wrap items-center gap-2"
    :aria-label="$t('dashboard.quick_actions.title')"
    data-testid="dashboard-quick-actions"
  >
    <router-link
      v-for="action in actions"
      :key="action.key"
      :to="action.to"
      :data-testid="`quick-action-${action.key}`"
      class="min-h-[44px] px-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-600 text-slate-700 dark:text-slate-200 text-xs sm:text-sm font-black flex items-center gap-2 transition active:scale-95 cursor-pointer select-none"
    >
      <component :is="action.icon" class="w-4 h-4 shrink-0 text-slate-500 dark:text-slate-400" />
      <span class="whitespace-nowrap">{{ action.label }}</span>
    </router-link>
    <router-link
      to="/all-screens"
      data-testid="quick-action-all-screens"
      class="min-h-[44px] px-3.5 rounded-2xl text-theme-primary text-xs sm:text-sm font-black flex items-center gap-2 hover:bg-theme-light transition active:scale-95 cursor-pointer select-none"
    >
      <LayoutGrid class="w-4 h-4 shrink-0" />
      <span class="whitespace-nowrap">{{ $t('dashboard.quick_actions.all_screens') }}</span>
    </router-link>
  </nav>
</template>
<script setup>
import { LayoutGrid } from 'lucide-vue-next';

defineProps({
  actions: { type: Array, default: () => [] },
});
</script>
