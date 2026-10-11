<script setup>
import { ChevronRight, Settings } from 'lucide-vue-next';
</script>

<template>
  <router-link
    to="/super-admin/settings"
    data-testid="platform-settings-link"
    class="group min-h-11 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 hover:border-theme-primary/50 rounded-2xl p-4 sm:p-5 shadow-sm flex items-center gap-3 transition active:scale-[0.99]"
  >
    <span class="w-10 h-10 rounded-xl bg-theme-light text-theme-primary flex items-center justify-center shrink-0">
      <Settings class="w-5 h-5" />
    </span>
    <span class="flex-1 min-w-0">
      <span class="block text-sm font-black text-slate-900 dark:text-white">{{ $t('super.nav_settings') }}</span>
      <span class="block text-[11px] text-slate-500 dark:text-slate-400 font-medium">
        {{ $t('super.platform_settings.dashboard_link.description') }}
      </span>
    </span>
    <span class="hidden sm:inline text-xs font-bold text-theme-primary">
      {{ $t('super.platform_settings.dashboard_link.open') }}
    </span>
    <ChevronRight class="w-4 h-4 text-theme-primary shrink-0 rtl:rotate-180" />
  </router-link>
</template>
